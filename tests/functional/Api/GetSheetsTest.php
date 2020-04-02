<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\FunctionalTests\Api;

use Keboola\OneDriveExtractor\Exception\InvalidFileTypeException;
use Keboola\OneDriveExtractor\Exception\ResourceNotFoundException;
use Keboola\OneDriveExtractor\FunctionalTests\Fixtures;
use PHPUnit\Framework\Assert;

class GetSheetsTest extends BaseTest
{
    /**
     * @dataProvider getValidFiles
     */
    public function testValidFile(string $filePath, array $expected): void
    {
        $file = $this->fixtures->getDrive()->getFile($filePath);
        $worksheets = iterator_to_array($this->api->getWorksheets($file->getDriveId(), $file->getFileId()));

        // Compare with expected data
        $serialized = json_decode((string) json_encode($worksheets), true);
        foreach ($serialized as &$worksheet) {
            // Dynamic values: assert not empty and ignore
            Assert::assertNotEmpty($worksheet['driveId']);
            Assert::assertNotEmpty($worksheet['fileId']);
            Assert::assertNotEmpty($worksheet['worksheetId']);
            unset($worksheet['driveId']);
            unset($worksheet['fileId']);
            unset($worksheet['worksheetId']);
        }
        Assert::assertSame($expected, $serialized);
    }

    /**
     * @dataProvider getFilesWithInvalidType
     */
    public function testFileWithInvalidFileType(string $filePath): void
    {
        $file = $this->fixtures->getDrive()->getFile($filePath);
        $this->expectException(InvalidFileTypeException::class);
        $this->expectExceptionMessage(
            'It looks like the specified file is not in the "XLSX" Excel format. ' .
            'Error: "AccessDenied: Could not obtain a WAC access token."'
        );
        iterator_to_array($this->api->getWorksheets($file->getDriveId(), $file->getFileId()));
    }

    public function testFileNotFound(): void
    {
        $driveId = $this->fixtures->getDrive()->getFile(Fixtures\FixturesCatalog::FILE_EMPTY)->getDriveId();
        $fileId = '01GQDMCCPK5MFK6QCSJFC2HYWA7AABCDEF';  // not exists
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('The resource could not be found.');
        iterator_to_array($this->api->getWorksheets($driveId, $fileId));
    }

    public function testDriveNotFound(): void
    {
        $driveId = 'b!nZgsjp3RK0aRFp01PZWjKUicqho1KehCtKM1UhLEWybvgM_dt6mJRKV571234567'; // not exists
        $fileId = $this->fixtures->getDrive()->getFile(Fixtures\FixturesCatalog::FILE_EMPTY)->getFileId();
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('The resource could not be found.');
        iterator_to_array($this->api->getWorksheets($driveId, $fileId));
    }

    public function getValidFiles(): array
    {
        return [
            'empty' => [
                Fixtures\FixturesCatalog::FILE_EMPTY,
                [
                    [
                        'position' => 0,
                        'name' => 'Sheet1',
                        'title' => 'Sheet1',
                        'visible' => true,
                        'header' => [],
                    ],
                ],
            ],
            'hidden_sheet' => [
                Fixtures\FixturesCatalog::FILE_HIDDEN_SHEET,
                [
                    [
                        'position' => 0,
                        'name' => 'Sheet1',
                        'title' => 'Sheet1',
                        'visible' => true,
                        'header' => ['Col_1', 'Col_2', 'Col_3'],
                    ],
                    [
                        'position' => 1,
                        'name' => 'Zošit 2',
                        'title' => 'Zošit 2',
                        'visible' => true,
                        'header' => ['Col_1', 'Col_2', 'Col_3'],
                    ],
                    [
                        'position' => 2,
                        'name' => 'Hidden Sheet 3',
                        'title' => 'Hidden Sheet 3 (hidden)',
                        'visible' => false,
                        'header' => ['Col_4', 'Col_5', 'Col_6'],
                    ],
                    [
                        'position' => 3,
                        'name' => 'sheet=4',
                        'title' => 'sheet=4',
                        'visible' => true,
                        'header' => ['Col_1', 'Col_2', 'Col_3'],
                    ],
                ],
            ],
            'one_sheet' => [
                Fixtures\FixturesCatalog::FILE_ONE_SHEET,
                [
                    [
                        'position' => 0,
                        'name' => 'Only One Sheet',
                        'title' => 'Only One Sheet',
                        'visible' => true,
                        'header' => ['Col_1', 'Col_2', 'Col_3'],
                    ],
                ],
            ],
            'only_header' => [
                Fixtures\FixturesCatalog::FILE_ONLY_HEADER,
                [
                    [
                        'position' => 0,
                        'name' => 'Sheet1',
                        'title' => 'Sheet1',
                        'visible' => true,
                        'header' => ['Col1', 'Col2', 'Col3', 'Col4'],
                    ],
                ],
            ],
            'special_cases' => [
                Fixtures\FixturesCatalog::FILE_SPECIAL_CASES,
                [
                    [
                        'position' => 0,
                        'name' => 'Special 123úěščř!@#$%^',
                        'title' => 'Special 123úěščř!@#$%^',
                        'visible' => true,
                        'header' =>
                            [
                                'Duplicate',
                                'Duplicate-1',
                                'column-3',
                                'column-4',
                                'Special_123_uescr',
                                'column-6',
                                'column-7',
                            ],
                    ],
                ],
            ],
            'table_offset' => [
                Fixtures\FixturesCatalog::FILE_TABLE_OFFSET,
                [
                    [
                        'position' => 0,
                        'name' => 'Sheet 1',
                        'title' => 'Sheet 1',
                        'visible' => true,
                        'header' =>
                            [
                                'Segment',
                                'column-2',
                                'Country',
                                'Duplicate',
                                'Duplicate-1',
                                'Product',
                                'Discount_Band',
                                'Units_Sold',
                                'column-9',
                                'column-10',
                            ],
                    ],
                ],
            ],
        ];
    }

    public function getFilesWithInvalidType(): array
    {
        return [
            'csv-file' => [Fixtures\FixturesCatalog::FILE_CSV],
            'ods-file' => [Fixtures\FixturesCatalog::FILE_ODS],
            'odt-file' => [Fixtures\FixturesCatalog::FILE_ODT],
            'xls-file' => [Fixtures\FixturesCatalog::FILE_XLS],
        ];
    }
}
