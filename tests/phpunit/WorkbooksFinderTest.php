<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Tests;

use Keboola\OneDriveExtractor\Api\Model\File;
use Keboola\OneDriveExtractor\Api\WorkbooksFinder;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

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
