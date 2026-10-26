<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Tests;

use ArrayIterator;
use Keboola\OneDriveExtractor\Api\Api;
use Keboola\OneDriveExtractor\Api\Batch\BatchRequest;
use Keboola\OneDriveExtractor\Api\Model\File;
use Keboola\OneDriveExtractor\Api\WorkbooksFinder;
use Microsoft\Graph\Http\GraphResponse;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use RuntimeException;

class WorkbooksFinderTest extends TestCase
{
    public function testMapSearchItemToFile(): void
    {
        $file = WorkbooksFinder::mapSearchItemToFile(
            [
                'id' => 'file123',
                'name' => 'report.xlsx',
                'parentReference' => ['driveId' => 'drive123'],
            ],
            'report'
        );

        Assert::assertInstanceOf(File::class, $file);
        Assert::assertSame(
            [
                'driveId' => 'drive123',
                'fileId' => 'file123',
                'name' => 'report.xlsx',
                'path' => 'shared',
            ],
            $file === null ? null : $file->jsonSerialize()
        );
    }

    public function testMapSearchItemToFileWithFolderPath(): void
    {
        $file = WorkbooksFinder::mapSearchItemToFile(
            [
                'id' => 'file123',
                'name' => 'report.xlsx',
                'parentReference' => ['driveId' => 'drive123', 'path' => '/drive/root:/dir1/dir2'],
            ],
            ''
        );

        Assert::assertInstanceOf(File::class, $file);
        Assert::assertSame('shared/dir1/dir2', $file === null ? null : $file->jsonSerialize()['path']);
    }

    public function testSearchApiNotCalledWhenBatchFindsFile(): void
    {
        $found = new File('file123', 'drive123', 'report.xlsx', ['my']);
        $api = $this->createApiMock([$found]);
        $api->expects(self::never())->method('post');

        $files = iterator_to_array((new WorkbooksFinder($api, new TestLogger()))->search('report'));

        Assert::assertSame([$found], $files);
    }

    public function testSearchApiCalledWhenBatchFindsNothing(): void
    {
        $response = $this->createMock(GraphResponse::class);
        $response->method('getBody')->willReturn([
            'value' => [[
                'hitsContainers' => [[
                    'hits' => [
                        ['resource' => [
                            'id' => 'file123',
                            'name' => 'report.xlsx',
                            'parentReference' => ['driveId' => 'drive123'],
                        ]],
                        ['resource' => [
                            'id' => 'file456',
                            'name' => 'report.docx',
                            'parentReference' => ['driveId' => 'drive123'],
                        ]],
                    ],
                ]],
            ]],
        ]);

        $api = $this->createApiMock([]);
        $api
            ->expects(self::once())
            ->method('post')
            ->with(
                '/search/query',
                [],
                self::callback(function (array $body): bool {
                    $request = $body['requests'][0];
                    return $request['entityTypes'] === ['driveItem']
                        && $request['query']['queryString'] === '"report" AND filetype:xlsx';
                }),
                [],
                WorkbooksFinder::SEARCH_API_MAX_ATTEMPTS
            )
            ->willReturn($response);

        $files = iterator_to_array((new WorkbooksFinder($api, new TestLogger()))->search('report.xlsx'));

        Assert::assertSame(
            [['driveId' => 'drive123', 'fileId' => 'file123', 'name' => 'report.xlsx', 'path' => 'shared']],
            array_map(fn(File $file) => $file->jsonSerialize(), $files)
        );
    }

    public function testSearchApiErrorIsLoggedAndIgnored(): void
    {
        $api = $this->createApiMock([]);
        $api->method('post')->willThrowException(new RuntimeException('Search failed.'));
        $logger = new TestLogger();

        $files = iterator_to_array((new WorkbooksFinder($api, $logger))->search('report'));

        Assert::assertSame([], $files);
        Assert::assertTrue($logger->hasWarningThatContains('Search failed.'));
    }

    /**
     * @param File[] $batchFiles files returned by the personal drive, shared files and sites sources
     * @return Api&MockObject
     */
    private function createApiMock(array $batchFiles): Api
    {
        $batch = $this->createMock(BatchRequest::class);
        $batch->method('addRequest')->willReturnSelf();
        $batch->method('execute')->willReturn(new ArrayIterator($batchFiles));

        $api = $this->createMock(Api::class);
        $api->method('createBatchRequest')->willReturn($batch);
        $api->method('getSitesDrives')->willReturn(new ArrayIterator([]));
        return $api;
    }

    /**
     * @dataProvider getSkippedSearchItems
     */
    public function testMapSearchItemToFileSkipped(array $item, string $search): void
    {
        Assert::assertNull(WorkbooksFinder::mapSearchItemToFile($item, $search));
    }

    public function getSkippedSearchItems(): array
    {
        $valid = [
            'id' => 'file123',
            'name' => 'report.xlsx',
            'parentReference' => ['driveId' => 'drive123'],
        ];

        return [
            'not-xlsx' => [array_merge($valid, ['name' => 'report.docx']), 'report'],
            'name-does-not-match' => [$valid, 'invoice'],
            'missing-id' => [['name' => 'report.xlsx', 'parentReference' => ['driveId' => 'drive123']], ''],
            'missing-name' => [['id' => 'file123', 'parentReference' => ['driveId' => 'drive123']], ''],
            'missing-parent-reference' => [['id' => 'file123', 'name' => 'report.xlsx'], ''],
            'parent-reference-not-array' => [array_merge($valid, ['parentReference' => 'foo']), ''],
            'missing-drive-id' => [array_merge($valid, ['parentReference' => []]), ''],
            'empty-drive-id' => [array_merge($valid, ['parentReference' => ['driveId' => '']]), ''],
            'empty-id' => [array_merge($valid, ['id' => '']), ''],
            'empty-name' => [array_merge($valid, ['name' => '']), ''],
        ];
    }
}
