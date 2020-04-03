<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api;

use Iterator;
use ArrayIterator;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Retry\RetryProxy;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\CallableRetryPolicy;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphResponse;
use Keboola\OneDriveExtractor\Api\Batch\BatchRequest;
use Keboola\OneDriveExtractor\Api\Model\Drive;
use Keboola\OneDriveExtractor\Api\Model\File;
use Keboola\OneDriveExtractor\Api\Model\Site;
use Keboola\OneDriveExtractor\Api\Model\SheetContent;
use Keboola\OneDriveExtractor\Api\Model\TableHeader;
use Keboola\OneDriveExtractor\Api\Model\Worksheet;
use Keboola\OneDriveExtractor\Exception\ResourceNotFoundException;
use Keboola\OneDriveExtractor\Exception\SheetEmptyException;
use Keboola\OneDriveExtractor\Exception\UnexpectedCountException;
use Keboola\OneDriveExtractor\Exception\UnexpectedValueException;

class Api
{
    private const RETRY_HTTP_CODES = [504]; // retry only on Gateway Timeout

    private Graph $graphApi;

    private LoggerInterface $logger;

    public function __construct(Graph $graphApi, LoggerInterface $logger)
    {
        $this->graphApi = $graphApi;
        $this->logger = $logger;
    }

    public function getAccountName(): string
    {
        $response = $this->get('/me?$select=userPrincipalName')->getBody();
        return (string) $response['userPrincipalName'];
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

        // Convert to iterator (in will be able to load per parts in future)
        $iterator = new ArrayIterator($rows);

        // Log
        $this->logger->info(sprintf(
            'Loaded sheet with %d rows, header: %s',
            $iterator->count(),
            Helpers::formatIterable($header->getColumns())
        ));

        // Encapsulate
        return SheetContent::from($header, $iterator);
    }

    public function getWorksheetHeader(string $driveId, string $fileId, string $worksheetId): TableHeader
    {
        // Table header is first row in worksheet
        // Table can be shifted because we use "usedRange".
        $endpoint = '/drives/{driveId}/items/{fileId}/workbook/worksheets/{worksheetId}';
        $uri = $endpoint . '/usedRange(valuesOnly=true)/row(row=0)?$select=address,text';
        $body = $this
            ->get($uri, ['driveId' => $driveId, 'fileId' => $fileId, 'worksheetId' => $worksheetId])
            ->getBody();
        $header = TableHeader::from($body['address'], $body['text'][0]);

        // Log
        $this->logger->info(sprintf('Loaded sheet header: %s', Helpers::formatIterable($header->getColumns())));

        return $header;
    }

    public function getWorksheetId(string $driveId, string $fileId, int $position): string
    {
        // Check position value, must be greater than zero
        if ($position < 0) {
            throw new UnexpectedValueException(sprintf(
                'Worksheet position must be greater than zero. Given "%d".',
                $position
            ));
        }

        // Load list of worksheets in workbook
        $uri = '/drives/{driveId}/items/{fileId}/workbook/worksheets?$select=id,name,position';
        $body = $this->get($uri, ['driveId' => $driveId, 'fileId' => $fileId])->getBody();

        // Search by position
        $worksheet = null;
        foreach ($body['value'] as $data) {
            if ($data['position'] === $position) {
                $worksheet = $data;
                break;
            }
        }

        // Log and return
        if ($worksheet) {
            $this->logger->info(sprintf(
                'Found worksheet "%s" at position "%s".',
                $worksheet['name'],
                $position
            ));
            return $worksheet['id'];
        }

        throw new ResourceNotFoundException(sprintf('No worksheet at position "%d".', $position));
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
        $finder = new WorkbooksFinder($this, $this->logger);
        return $finder->search($search);
    }

    public function createBatchRequest(): BatchRequest
    {
        return new BatchRequest($this);
    }

    public function get(string $uri, array $params = []): GraphResponse
    {
        return $this->executeWithRetry('GET', $uri, $params);
    }

    public function post(string $uri, array $params = [], array $body = []): GraphResponse
    {
        return $this->executeWithRetry('POST', $uri, $params, $body);
    }

    private function executeWithRetry(string $method, string $uri, array $params = [], array $body = []): GraphResponse
    {
        $backOffPolicy = new ExponentialBackOffPolicy(100, 2.0, 2000);
        $retryPolicy = new CallableRetryPolicy(function (\Throwable $e) {
            if ($e instanceof RequestException) {
                $response = $e->getResponse();
                // Retry only on defined HTTP codes
                if ($response && in_array($response->getStatusCode(), self::RETRY_HTTP_CODES, true)) {
                    return true;
                }
            }

            return false;
        });

        $proxy = new RetryProxy($retryPolicy, $backOffPolicy);
        return $proxy->call(function () use ($method, $uri, $params, $body) {
            return $this->execute($method, $uri, $params, $body);
        });
    }

    private function execute(string $method, string $uri, array $params = [], array $body = []): GraphResponse
    {
        $uri = Helpers::replaceParamsInUri($uri, $params);
        $request = $this->graphApi->createRequest($method, $uri);
        if ($body) {
            $request->attachBody($body);
        }

        try {
            return $request->execute();
        } catch (RequestException $e) {
            throw Helpers::processRequestException($e);
        }
    }
}
