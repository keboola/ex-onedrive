<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Batch;

use Iterator;
use InvalidArgumentException;
use GuzzleHttp\Exception\RequestException;
use Keboola\OneDriveExtractor\Exception\BatchRequestException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphResponse;

/**
 * Microsoft Graph API allows combine requests to batch, and run them as single request.
 * See: https://docs.microsoft.com/en-us/graph/json-batching
 */
class BatchRequest
{
    private Graph $graphApi;

    private ?int $limit;

    private int $idCounter = 1;

    private int $processedCount;

    /** @var array|Request[] */
    private array $requests = [];

    public function __construct(Graph $graphApi, ?int $limit = null)
    {
        $this->graphApi = $graphApi;
        $this->limit = $limit;
    }

    public function addRequest(
        string $uriTemplate,
        array $uriArgs = [],
        ?callable $responseMapper = null,
        string $method = 'GET'
    ): self {
        $id = (string) $this->idCounter++;
        $this->requests[$id] = new Request($id, $uriTemplate, $uriArgs, $responseMapper, $method);
        return $this;
    }

    public function execute(): Iterator
    {
        $this->processedCount = 0;
        $response = $this->runBatchRequest();
        do {
            yield from $this->processBatchResponse($response);
            $response = $this->getNextPage($response);
        } while ($response !== null);
    }

    private function getNextPage(GraphResponse $response): ?GraphResponse
    {
        // See: https://docs.microsoft.com/en-us/graph/paging
        /** @var string|null $nextLink */
        $nextLink = $response->getNextLink();
        if ($nextLink === null) {
            return null;
        }

        return $this->graphApi->createRequest('GET', $nextLink)->execute();
    }

    private function runBatchRequest(): GraphResponse
    {
        $retry = 3;
        while (true) {
            try {
                return $this->graphApi
                    ->createRequest('POST', '/$batch')
                    ->attachBody([
                        'requests' =>
                            array_map(fn(Request $request) => $request->toArray(), array_values($this->requests)),
                    ])
                    ->execute();
            } catch (RequestException $e) {
                // Retry only if 504 Gateway Timeout
                $response = $e->getResponse();
                if ($retry-- <= 0 || !$response || $response->getStatusCode() !== 504) {
                    throw $e;
                }
            }
        }
    }

    private function processBatchResponse(GraphResponse $batchResponse): Iterator
    {
        $responses = $batchResponse->getBody()['responses'];
        assert(is_array($responses));

        foreach ($responses as $response) {
            $id = $response['id'];
            assert(is_string($id));
            $status = $response['status'];
            assert(is_int($status));
            $body = $response['body'] ?? [];
            $request = $this->getRequestById($id);
            yield from $this->processResponse($request, $status, $body);
        }
    }

    private function processResponse(Request $request, int $status, array $body): Iterator
    {
        // Request from batch failed
        if ($status !== 200) {
            throw new BatchRequestException(sprintf(
                'Unexpected status "%d" for request "%s": %s, %s',
                $status,
                $request->getUri(),
                $body['error']['code'] ?? '',
                $body['error']['message'] ?? '',
            ));
        }

        // Map response body (eg. to files)
        $mapper = $request->getResponseMapper();
        $values = $mapper ? $mapper($body) : [$body];
        assert($values instanceof Iterator);
        foreach ($values as $key => $value) {
            // End if over limit
            if ($this->limit && $this->processedCount >= $this->limit) {
                break;
            }

            // It is needed to specify key, otherwise it would be overwritten
            // ... because method is called multiple times, and without specifications are keys: 0, 1, 2 ...
            yield $this->processedCount => $value;

            // Increase processed
            $this->processedCount++;
        }
    }

    private function getRequestById(string $id): Request
    {
        if (!isset($this->requests[$id])) {
            throw new InvalidArgumentException(sprintf('Request with id "%s" not found.', $id));
        }

        return $this->requests[$id];
    }
}
