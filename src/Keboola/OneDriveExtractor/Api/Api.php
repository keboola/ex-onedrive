<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api;

use GuzzleHttp\Exception\RequestException;
use Iterator;
use ArrayIterator;
use GuzzleHttp\Exception\ClientException;
use Keboola\OneDriveExtractor\Api\Batch\BatchRequest;
use Keboola\OneDriveExtractor\Api\Model\Drive;
use Keboola\OneDriveExtractor\Api\Model\File;
use Keboola\OneDriveExtractor\Api\Model\Site;
use Keboola\OneDriveExtractor\Api\Model\SheetContent;
use Keboola\OneDriveExtractor\Api\Model\TableHeader;
use Keboola\OneDriveExtractor\Api\Model\Worksheet;
use Keboola\OneDriveExtractor\Exception\InvalidFileTypeException;
use Keboola\OneDriveExtractor\Exception\ResourceNotFoundException;
use Keboola\OneDriveExtractor\Exception\SheetEmptyException;
use Keboola\OneDriveExtractor\Exception\UnexpectedCountException;
use Keboola\OneDriveExtractor\Exception\UnexpectedValueException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphResponse;

class Api
{
    private Graph $graphApi;

    public function __construct(Graph $graphApi)
    {
        $this->graphApi = $graphApi;
    }

    public function getAccountName(): string
    {
        $response = $this->get('/me?$select=userPrincipalName')->getBody();
        $account = $response['userPrincipalName'];
        assert(is_string($account));
        return $account;
    }

    public function getWorksheetContent(string $driveId, string $fileId, string $worksheetId): SheetContent
    {
        $endpoint = '/drives/{driveId}/items/{fileId}/workbook/worksheets/{worksheetId}';
        $uri = $endpoint . '/usedRange(valuesOnly=true)?$select=address,text';

        // Get rows
        $response = $this->get($uri, ['driveId' => $driveId, 'fileId' => $fileId, 'worksheetId' => $worksheetId]);
        $body = $response->getBody();
        $rows = $body['text'];

        // Pagination is not supported by this endpoint
        /** @var string|null $nextLink */
        $nextLink = $response->getNextLink();
        assert($nextLink === null);

        // Parse header
        $address = $body['address'];
        $headerCells = array_shift($rows);
        $header = TableHeader::from($address, $headerCells);
        if (empty($header->getColumns())) {
            throw new SheetEmptyException('Spreadsheet is empty.');
        }

        // Load rows in parts
        $iterator = new ArrayIterator($rows);

        // Encapsulate
        return SheetContent::from($header, $iterator);
    }

    public function getWorksheetHeader(string $driveId, string $fileId, string $worksheetId): TableHeader
    {
        // Table header is first row from worksheet
        // Table can be shifted because we use "usedRange".
        $endpoint = '/drives/{driveId}/items/{fileId}/workbook/worksheets/{worksheetId}';
        $uri = $endpoint . '/usedRange(valuesOnly=true)/row(row=0)?$select=address,text';
        $body = $this
            ->get($uri, ['driveId' => $driveId, 'fileId' => $fileId, 'worksheetId' => $worksheetId])
            ->getBody();
        return TableHeader::from($body['address'], $body['text'][0]);
    }

    public function getWorksheetId(string $driveId, string $fileId, int $worksheetPosition): string
    {
        if ($worksheetPosition < 0) {
            throw new UnexpectedValueException(sprintf(
                'Worksheet position must be greater than zero. Given "%d".',
                $worksheetPosition
            ));
        }

        // Load list of worksheets in workbook
        $uri = '/drives/{driveId}/items/{fileId}/workbook/worksheets?$select=id,position';
        $body = $this
            ->get($uri, ['driveId' => $driveId, 'fileId' => $fileId])
            ->getBody();

        foreach ($body['value'] as $data) {
            if ($data['position'] === $worksheetPosition) {
                return $data['id'];
            }
        }

        throw new ResourceNotFoundException(sprintf('No worksheet at position "%d".', $worksheetPosition));
    }

    /**
     * @return Iterator|Worksheet[]
     */
    public function getWorksheets(string $driveId, string $fileId): Iterator
    {
        // Load list of worksheets in workbook
        $uri = '/drives/{driveId}/items/{fileId}/workbook/worksheets?$select=id,position,name,visibility';
        $body = $this
            ->get($uri, ['driveId' => $driveId, 'fileId' => $fileId])
            ->getBody();

        // Map to object and load header in batch request
        $batch = $this->createBatchRequest();
        foreach ($body['value'] as $data) {
            $worksheet = Worksheet::from($data, $driveId, $fileId);
            $endpoint = '/drives/{driveId}/items/{fileId}/workbook/worksheets/{worksheetId}';
            $uri = $endpoint . '/usedRange(valuesOnly=true)/row(row=0)?$select=address,text';
            $args = ['driveId' => $driveId, 'fileId' => $fileId, 'worksheetId' => $worksheet->getWorksheetId()];
            $batch->addRequest($uri, $args, function (array $body) use ($worksheet) {
                $header = TableHeader::from($body['address'], $body['text'][0]);
                $worksheet->setHeader($header);
                yield $worksheet;
            });
        }

        // Load headers for worksheets in one request, sort by position
        $worksheets = iterator_to_array($batch->execute());
        usort($worksheets, fn(Worksheet $a, Worksheet $b) => $a->getPosition() - $b->getPosition());
        yield from $worksheets;
    }

    /**
     * @return Iterator|Drive[]
     */
    public function getSitesDrives(): Iterator
    {
        $batch = $this->createBatchRequest();
        foreach ($this->getSites() as $site) {
            $siteId = urlencode($site->getId());
            $batch->addRequest(
                '/sites/{siteId}/drives?$select=id,name',
                ['siteId' => $siteId],
                function (array $body) use ($site) {
                    foreach ($body['value'] as $data) {
                        yield Drive::from($data, $site);
                    }
                }
            );
        }

        // Fetch all in one request
        return $batch->execute();
    }

    /**
     * @return Iterator|Site[]
     */
    public function getSites(): Iterator
    {
        $response = $this->get('/sites?search=&$select=id,name');
        assert($response instanceof GraphResponse);
        foreach ($response->getBody()['value'] as $data) {
            yield Site::from($data);
        }
    }

    public function getSite(string $name): Site
    {
        $response = $this->get('/sites?search={name}&$select=id,name', ['name' => $name]);
        $body = $response->getBody();
        $count = count($body['value']);
        if ($count === 1) {
            $siteData = $body['value'][0];
            return Site::from($siteData);
        } elseif ($count === 0) {
            throw new ResourceNotFoundException(sprintf('Site "%s" not found.', $name));
        } else {
            throw new UnexpectedCountException(sprintf('Multiple sites found when searching for "%s".', $name));
        }
    }


    /**
     * @return Iterator|File[]
     */
    public function searchWorkbooks(string $search = ''): Iterator
    {
        $finder = new WorkbooksFinder($this);
        return $finder->search($search);
    }

    public function createBatchRequest(): BatchRequest
    {
        return new BatchRequest($this->graphApi);
    }

    public function get(string $uri, array $params = []): GraphResponse
    {
        try {
            return $this->execute('GET', Helpers::replaceParamsInUri($uri, $params));
        } catch (ClientException $e) {
            $error = Helpers::getErrorFromRequestException($e);
            if ($error === 'AccessDenied: Could not obtain a WAC access token.') {
                $msg = 'It looks like the specified file is not in the "XLSX" Excel format. Error: "%s"';
                throw new InvalidFileTypeException(sprintf($msg, $error), 0, $e);
            } elseif ($error && strpos($error, 'ItemNotFound:') === 0) {
                throw new ResourceNotFoundException('The resource could not be found.', 0, $e);
            }

            throw $e;
        }
    }

    private function execute(string $method, string $uri): GraphResponse
    {
        $retry = 3;
        while (true) {
            try {
                return $this->graphApi->createRequest($method, $uri)->execute();
            } catch (RequestException $e) {
                // Retry only if 504 Gateway Timeout
                $response = $e->getResponse();
                if ($retry-- <= 0 || !$response || $response->getStatusCode() !== 504) {
                    throw $e;
                }
            }
        }
    }
}
