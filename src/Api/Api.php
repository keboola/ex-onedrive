<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api;

use ArrayIterator;
use GuzzleHttp\Exception\RequestException;
use Iterator;
use Keboola\OneDriveExtractor\Api\Batch\BatchRequest;
use Keboola\OneDriveExtractor\Api\Model\Drive;
use Keboola\OneDriveExtractor\Api\Model\File;
use Keboola\OneDriveExtractor\Api\Model\SheetContent;
use Keboola\OneDriveExtractor\Api\Model\Site;
use Keboola\OneDriveExtractor\Api\Model\TableHeader;
use Keboola\OneDriveExtractor\Api\Model\TableRange;
use Keboola\OneDriveExtractor\Api\Model\Worksheet;
use Keboola\OneDriveExtractor\Exception\GatewayTimeoutException;
use Keboola\OneDriveExtractor\Exception\ResourceNotFoundException;
use Keboola\OneDriveExtractor\Exception\SheetEmptyException;
use Keboola\OneDriveExtractor\Exception\UnexpectedCountException;
use Keboola\OneDriveExtractor\Exception\UnexpectedValueException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphResponse;
use Psr\Log\LoggerInterface;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\CallableRetryPolicy;
use Retry\RetryProxy;

class Api
{
    // API can work with max. 5M cells
    public const DEFAULT_CELLS_PER_BULK = 1_000_000;

    public const RETRY_MAX_TRIES = 14;

    public const RETRY_HTTP_CODES = [
        409, // 409 Conflict
        429, // 429 Too Many Requests
        500, // 500 Internal Serve Error
        502, // 502 Bad Gateway
        503, // 503 Service Unavailable
        504, // 504 Gateway Timeout
    ];

    private Graph $graphApi;

    private LoggerInterface $logger;

    private int $maxAttempts;

    public function __construct(Graph $graphApi, LoggerInterface $logger, int $maxAttempts)
    {
        $this->graphApi = $graphApi;
        $this->logger = $logger;
        $this->maxAttempts = $maxAttempts;
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getWorkbookSessionId(string $driveId, string $fileId): ?string
    {
        $uri = '/drives/{driveId}/items/{fileId}/workbook/createSession';
        $response = $this->post(
            $uri,
            [
                'driveId' => $driveId,
                'fileId' => $fileId,
            ],
            [
                'persistChanges' => false,
            ],
            [
                'Prefer' => 'respond-async',
            ],
        );

        switch ($response->getStatus()) {
            case 201:
                return $response->getBody()['id'];
            case 202:
                $responseHeader = $response->getHeaders();

                $sessionLocation = current($responseHeader['Location']);

                $status = 'running';
                while ($status === 'running') {
                    sleep(2);
                    $session = $this->get($sessionLocation)->getBody();
                    $status = $session['status'];
                }

                if ($status !== 'succeeded') {
                    $this->logger->info('The workbook session could not be created.');
                    return null;
                }

                $sessionResource = $this->get($session['resourceLocation'])->getBody();

                return $sessionResource['id'];
            default:
                $this->logger->info('The workbook session could not be created.');
                return null;
        }
    }

    public function getAccountName(): string
    {
        $response = $this->get('/me?$select=userPrincipalName')->getBody();
        return (string) $response['userPrincipalName'];
    }

    public function getUsedRange(string $driveId, string $fileId, string $worksheetId, ?string $sessionId): TableRange
    {
        $endpoint = '/drives/{driveId}/items/{fileId}/workbook/worksheets/{worksheetId}';
        $uri = $endpoint . '/usedRange(valuesOnly=true)?$select=address';
        $headers = [];
        if ($sessionId) {
            $headers['Workbook-Session-Id'] = $sessionId;
        }
        $response = $this->get(
            $uri,
            ['driveId' => $driveId, 'fileId' => $fileId, 'worksheetId' => $worksheetId],
            $headers
        );
        $body = $response->getBody();

        // Parse range
        $address = $body['address'];
        return TableRange::from($address);
    }

    public function getWorksheetContent(
        string $driveId,
        string $fileId,
        string $worksheetId,
        ?int $rowsLimit = null,
        int $cellsPerBulk = self::DEFAULT_CELLS_PER_BULK,
        ?string $sessionId = null
    ): SheetContent {
        $usedRange = $this->getUsedRange($driveId, $fileId, $worksheetId, $sessionId);
        $header = $this->getWorksheetHeader($driveId, $fileId, $worksheetId, $sessionId);

        // Is empty?
        if (empty($header->getColumns())) {
            throw new SheetEmptyException('Spreadsheet is empty.');
        }

        // Skip header row
        $rowsRange = $usedRange->skipRows($header->getRowsCount());

        // We don't need to load more rows in one bulk than the limit.
        $cellsLimit = $rowsLimit && $rowsRange ? $rowsLimit * $rowsRange->getColumnsCount() : null;
        $cellsPerBulk = $cellsLimit && $cellsLimit < $cellsPerBulk ? $cellsLimit : $cellsPerBulk;

        // Log total rows count
        $this->logger->info(sprintf(
            'Number of rows in the sheet: %d header + %d',
            $header->getRowsCount(),
            $rowsRange ? $rowsRange->getRowsCount() : 0
        ));

        // Log limit
        if ($rowsLimit) {
            $this->logger->info(sprintf('Configured rows limit: %d', $rowsLimit));
        }

        $iterator = $rowsRange ?
            $this->getRowsForRange($driveId, $fileId, $worksheetId, $rowsRange, $rowsLimit, $cellsPerBulk, $sessionId) :
            new ArrayIterator([]);
        return new SheetContent($header, $usedRange, $iterator);
    }

    public function getWorksheetHeader(
        string $driveId,
        string $fileId,
        string $worksheetId,
        ?string $sessionId
    ): TableHeader {
        // Table header is first row in worksheet
        // Table can be shifted because we use "usedRange".
        $endpoint = '/drives/{driveId}/items/{fileId}/workbook/worksheets/{worksheetId}';
        $uri = $endpoint . '/usedRange(valuesOnly=true)/row(row=0)?$select=address,text';
        $headers = [];
        if ($sessionId) {
            $headers['Workbook-Session-Id'] = $sessionId;
        }
        $body = $this
            ->get($uri, ['driveId' => $driveId, 'fileId' => $fileId, 'worksheetId' => $worksheetId], $headers)
            ->getBody();
        $header = TableHeader::from($body['address'], $body['text'][0]);

        // Log
        $this->logger->info(sprintf(
            'Sheet header (%s:%s): %s',
            $header->getStartCell(),
            $header->getEndCell(),
            Helpers::formatIterable($header->getColumns()),
        ));

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
        $sessionId = $this->getWorkbookSessionId($driveId, $fileId);
        $headers = [];
        if ($sessionId) {
            $headers['Workbook-Session-Id'] = $sessionId;
        }

        // Load list of worksheets in workbook
        $uri = '/drives/{driveId}/items/{fileId}/workbook/worksheets?$select=id,position,name,visibility';
        $body = $this
            ->get($uri, ['driveId' => $driveId, 'fileId' => $fileId], $headers)
            ->getBody();

        // Map to object and load header in batch request
        $batch = $this->createBatchRequest();
        foreach ($body['value'] as $data) {
            $worksheet = Worksheet::from($data, $driveId, $fileId);
            $endpoint = '/drives/{driveId}/items/{fileId}/workbook/worksheets/{worksheetId}';
            $uri = $endpoint . '/usedRange(valuesOnly=true)/row(row=0)?$select=address,text';
            $args = ['driveId' => $driveId, 'fileId' => $fileId, 'worksheetId' => $worksheet->getWorksheetId()];
            $batch->addRequest($uri, $args, function (array $body) use ($worksheet) {
                if (isset($body['address'])) {
                    $header = TableHeader::from($body['address'], $body['text'][0]);
                    $worksheet->setHeader($header);
                    yield $worksheet;
                }
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
        $siteIdsChecked = [];

        foreach ($this->getSites() as $site) {
            // Split ID parts, eg. "keboolads.sharepoint.com,7df65f25-e443-4c7e-af...."
            $siteIdParts = explode(',', $site->getId());
            $siteId = urlencode($siteIdParts[0]);
            if (in_array($siteId, $siteIdsChecked)) {
                continue;
            }

            $batch->addRequest(
                '/sites/{siteId}/drives?$select=id,name',
                ['siteId' => $siteId],
                function (array $body) use ($site) {
                    foreach ($body['value'] as $data) {
                        yield Drive::from($data, $site);
                    }
                }
            );

            $siteIdsChecked[] = $siteId;
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

    public function get(string $uri, array $params = [], array $headers = []): GraphResponse
    {
        return $this->executeWithRetry('GET', $uri, $params, [], $headers);
    }

    public function post(string $uri, array $params = [], array $body = [], array $headers = []): GraphResponse
    {
        return $this->executeWithRetry('POST', $uri, $params, $body, $headers);
    }

    public function createRetry(LoggerInterface $logger, int $maxAttempts = self::RETRY_MAX_TRIES): RetryProxy
    {
        $backOffPolicy = new ExponentialBackOffPolicy(1000);
        $retryPolicy = new CallableRetryPolicy(function (\Throwable $e) {
            // Always retry on gateway timeout
            if ($e instanceof GatewayTimeoutException) {
                return true;
            }

            if ($e instanceof RequestException) {
                // Retry only on defined HTTP codes
                if (in_array($e->getCode(), self::RETRY_HTTP_CODES, true)) {
                    return true;
                }

                // Retry if communication problems
                if (strpos($e->getMessage(), 'There were communication or server problems')) {
                    return true;
                }
            }

            return false;
        }, $maxAttempts);
        return new RetryProxy($retryPolicy, $backOffPolicy, $logger);
    }


    private function getRowsForRange(
        string $driveId,
        string $fileId,
        string $worksheetId,
        TableRange $range,
        ?int $rowsLimit,
        int $cellsPerBulk,
        ?string $sessionId
    ): Iterator {
        $rowsCount = 0;
        foreach ($range->split($cellsPerBulk, $rowsLimit) as $subRange) {
            $rowsForAddress = $this->getRowsForAddress(
                $driveId,
                $fileId,
                $worksheetId,
                $subRange->getAddress(),
                $sessionId
            );
            foreach ($rowsForAddress as &$row) {
                yield $row;
                $rowsCount++;
            };
        }

        $this->logger->info(sprintf('Exported all %d rows.', $rowsCount));
    }

    private function getRowsForAddress(
        string $driveId,
        string $fileId,
        string $worksheetId,
        string $address,
        ?string $sessionId
    ): ArrayIterator {
        $this->logger->info(sprintf('Exporting range "%s".', $address));
        $endpoint = '/drives/{driveId}/items/{fileId}/workbook/worksheets/{worksheetId}';
        $uri = $endpoint . '/range(address=\'{address}\')?$select=address,text';
        $headers = [];
        if ($sessionId) {
            $headers['Workbook-Session-Id'] = $sessionId;
        }
        $response = $this->get(
            $uri,
            [
                'driveId' => $driveId,
                'fileId' => $fileId,
                'worksheetId' => $worksheetId,
                'address' => $address,
            ],
            $headers
        );

        // Pagination is not supported by this endpoint
        /** @var string|null $nextLink */
        $nextLink = $response->getNextLink();
        if ($nextLink !== null) {
            throw new UnexpectedValueException('API response contains link to next page. It is not expected.');
        }

        $body = $response->getBody();
        return new ArrayIterator($body['text']);
    }

    private function executeWithRetry(
        string $method,
        string $uri,
        array $params = [],
        array $body = [],
        array $headers = []
    ): GraphResponse {
        return $this
            ->createRetry($this->logger, $this->maxAttempts)
            ->call(function () use ($method, $uri, $params, $body, $headers) {
                return $this->execute($method, $uri, $params, $body, $headers);
            });
    }

    private function execute(
        string $method,
        string $uri,
        array $params = [],
        array $body = [],
        array $headers = []
    ): GraphResponse {
        $uri = Helpers::replaceParamsInUri($uri, $params);
        $request = $this->graphApi->createRequest($method, $uri);
        if ($headers) {
            $request->addHeaders($headers);
        }
        if ($body) {
            $request->attachBody($body);
        }

        try {
            return $request->execute();
        } catch (RequestException $e) {
            # Log response of the failed API request
            $response = $e->getResponse();
            if ($response) {
                $body = $response->getBody();
                $body->rewind();
                $this->logger->error(sprintf(
                    'API request failed, uri: "%s", response: "%s".',
                    $e->getRequest()->getUri(),
                    $body->getContents(),
                ));
            }

            // Convert to user exception
            throw Helpers::processRequestException($e);
        }
    }
}
