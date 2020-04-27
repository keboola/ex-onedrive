<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\ApiTests;

use Throwable;
use PHPUnit\Framework\Assert;

class BatchRequestTest extends BaseTest
{
    public function testEmptyBatchRequest(): void
    {
        // Test for bug COM-214, when empty batch request resulted to "BadRequest: Invalid batch payload format."
        $batch = $this->api->createBatchRequest();
        Assert::assertCount(0, iterator_to_array($batch->execute()));
    }

    public function testExceptionProcessor(): void
    {
        $batch = $this->api->createBatchRequest();
        $batch->addRequest('/some/invalid/path1', [], null, function (Throwable $e): void {
            $this->logger->warning('Warning, request 1 failed.');
        });
        $batch->addRequest('/some/invalid/path2', [], null, function (Throwable $e): void {
            $this->logger->warning('Warning, request 2 failed.');
        });

        $results = iterator_to_array($batch->execute());
        Assert::assertCount(0, $results);

        $logs = array_map(fn(array $r) => $r['message'], $this->logger->records);
        sort($logs); // sort by value, order is not guaranteed by API
        Assert::assertSame([
            'Warning, request 1 failed.',
            'Warning, request 2 failed.',
        ], $logs);
    }
}
