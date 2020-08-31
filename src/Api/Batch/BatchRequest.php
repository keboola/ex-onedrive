<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Batch;

use Throwable;
use Iterator;
use NoRewindIterator;
use ArrayIterator;
use LimitIterator;
use InvalidArgumentException;
use Keboola\OneDriveExtractor\Api\Api;
use Keboola\OneDriveExtractor\Exception\BatchRequestException;
use Microsoft\Graph\Http\GraphResponse;

/**
 * Microsoft Graph API allows combine requests to batch, and run them as single request.
 * See: https://docs.microsoft.com/en-us/graph/json-batching
 */
class BatchRequest
{
    // https://docs.microsoft.com/en-us/graph/known-issues#limit-on-batch-size
    public const MAX_REQUESTS_PER_BATCH = 20;

    private Api $api;

    private ?int $limit;

    private int $idCounter = 1;

    private int $processedCount;

    /** @var array|Request[] */
    private array $requests = [];

    public function __construct(Api $api, ?int $limit = null)
    {
        $this->api = $api;
        $this->limit = $limit;
    }

    public function addRequest(
        string $uriTemplate,
        array $uriArgs = [],
        ?callable $responseMapper = null,
        ?callable $exceptionProcessor = null,
        string $method = 'GET'
    ): self {
        $id = (string) $this->idCounter++;
        $this->requests[$id] = new Request($id, $uriTemplate, $uriArgs, $responseMapper, $exceptionProcessor, $method);
        return $this;
    }

    public function execute(): Iterator
    {
        // Empty batch request cannot be executed, ... if empty => empty iterator is returned
        if ($this->requests) {
            $this->processedCount = 0;
            $responses = $this->runBatchRequest();
            foreach ($responses as $response) {
                do {
                    yield from $this->processBatchResponse($response);
                    $response = $this->getNextPage($response);
                } while ($response !== null);
            }
        }
    }

    private function getNextPage(GraphResponse $response): ?GraphResponse
    {
        // See: https://docs.microsoft.com/en-us/graph/paging
        /** @var string|null $nextLink */
        $nextLink = $response->getNextLink();
        if ($nextLink === null) {
            return null;
        }

        return $this->api->get($nextLink);
    }

    /**
     * @return GraphResponse[]
     */
    private function runBatchRequest(): array
    {
        /** @var GraphResponse[] $responses */
        $responses = [];

        $all = new NoRewindIterator(new ArrayIterator($this->requests));
        while ($all->valid()) {
            $batch = new LimitIterator($all, 0, self::MAX_REQUESTS_PER_BATCH);
            $requests = array_map(fn(Request $request) => $request->toArray(), array_values(iterator_to_array($batch)));
            $responses[] = $this->api->post('/$batch', [], [ 'requests' => $requests]);
        }

        return $responses;
    }

    private function processBatchResponse(GraphResponse $batchResponse): Iterator
    {
        $responses = $batchResponse->getBody()['responses'];
        assert(is_array($responses));

        foreach ($responses as $response) {
            $id = (string) $response['id'];
            $status = (int) $response['status'];
            $body = $response['body'] ?? [];
            $request = $this->getRequestById($id);

            try {
                yield from $this->processResponse($request, $status, $body);
            } catch (Throwable $e) {
                if ($request->hasExceptionProcessor()) {
                    $request->getExceptionProcessor()($e);
                    continue;
                }

                throw $e;
            }
        }
    }

    private function processResponse(Request $request, int $status, array $body): Iterator
    {
        // Request from batch failed, status != 2xx
        if ($status < 200 || $status >= 300) {
            throw new BatchRequestException(sprintf(
                'Unexpected status "%d" for request "%s": %s, %s',
                $status,
                $request->getUri(),
                $body['error']['code'] ?? '',
                $body['error']['message'] ?? '',
            ), $body['error']['message'], $status);
        }

        // Map response body (eg. to files)
        /** @var iterable $values */
        $values = $request->hasResponseMapper() ? $request->getResponseMapper()($body) : [$body];
        foreach ($values as $key => $value) {
            // End if over limit (eg. last page has 10 items, but we need only 4)
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
