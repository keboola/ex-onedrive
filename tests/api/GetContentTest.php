<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\ApiTests;

use Keboola\OneDriveExtractor\Exception\SheetEmptyException;
use Keboola\OneDriveExtractor\Fixtures\FixturesCatalog;
use PHPUnit\Framework\Assert;

class GetContentTest extends BaseTest
{
    /**
     * @dataProvider getFiles
     */
    public function testGetWorksheetHeader(string $fileName, int $worksheetPosition, array $expectedHeader): void
    {
        $fixture = $this->fixtures->getDrive()->getFile($fileName);
        $worksheetId = $this->api->getWorksheetId($fixture->getDriveId(), $fixture->getFileId(), $worksheetPosition);
        $header = $this->api->getWorksheetHeader($fixture->getDriveId(), $fixture->getFileId(), $worksheetId);
        Assert::assertSame($expectedHeader, json_decode((string) json_encode($header)));
    }

    public function testGetWorksheetHeaderEmptyFile(): void
    {
        $fixture = $this->fixtures->getDrive()->getFile(FixturesCatalog::FILE_EMPTY);
        $worksheetId = $this->api->getWorksheetId($fixture->getDriveId(), $fixture->getFileId(), 0);
        $header = $this->api->getWorksheetHeader($fixture->getDriveId(), $fixture->getFileId(), $worksheetId);
        Assert::assertSame([], json_decode((string) json_encode($header)));
    }

    /**
     * @dataProvider getFiles
     */
    public function testGetWorksheetContent(
        string $fileName,
        int $worksheetPosition,
        array $expectedHeader,
        array $expectedContent
    ): void {
        $fixture = $this->fixtures->getDrive()->getFile($fileName);
        $worksheetId = $this->api->getWorksheetId($fixture->getDriveId(), $fixture->getFileId(), $worksheetPosition);
        $table = $this->api->getWorksheetContent($fixture->getDriveId(), $fixture->getFileId(), $worksheetId);
        Assert::assertSame($expectedHeader, json_decode((string) json_encode($table->getHeader())));
        Assert::assertSame($expectedContent, iterator_to_array($table->getRows()));
    }

    public function testGetWorksheetContentEmptyFile(): void
    {
        $fixture = $this->fixtures->getDrive()->getFile(FixturesCatalog::FILE_EMPTY);
        $worksheetId = $this->api->getWorksheetId($fixture->getDriveId(), $fixture->getFileId(), 0);

        $this->expectException(SheetEmptyException::class);
        $this->expectExceptionMessage('Spreadsheet is empty.');
        $this->api->getWorksheetContent($fixture->getDriveId(), $fixture->getFileId(), $worksheetId);
    }

    public function getFiles(): array
    {
        return [
            'hidden-sheet' => [
                FixturesCatalog::FILE_HIDDEN_SHEET,
                2, // hidden sheet, see ListSheetsTest.php
                ['Col_4', 'Col_5', 'Col_6'],
                [
                    ['A', 'B', 'cell from hidden sheet'],
                    ['X', 'Y', 'Z'],
                ],
            ],
            'one-sheet' => [
                FixturesCatalog::FILE_ONE_SHEET,
                0,
                ['Col_1', 'Col_2', 'Col_3'],
                [
                    ['A', 'B', 'C'],
                    ['D', 'E', 'F'],
                ],
            ],
            'only-header' => [
                FixturesCatalog::FILE_ONLY_HEADER,
                0,
                ['Col1', 'Col2', 'Col3', 'Col4'],
                [],
            ],
            'special-cases' => [
                FixturesCatalog::FILE_SPECIAL_CASES,
                0,
                [
                    'Duplicate',
                    'Duplicate-1',
                    'column-3',
                    'column-4',
                    'Special_123_uescr',
                    'column-6',
                    'column-7',
                ],
                [
                    ['A', '10', '(2x empty header)', '', ' $1,618.50 ', '1/1/2014', '(empty col)',],
                    ['B', '20', 'a', '', ' $1,321.00 ', '1/1/2014', '',],
                    ['A', '30', 'b', '', ' $2,178.00 ', '1/6/2014', 'x',],
                    ['B', '40', 'a', '', ' $888.00 ', '1/6/2014', 'y',],
                    ['A', '50', 'b', '', ' $2,470.00 ', '1/6/2014', 'z',],
                    ['B', '60', 'a', '', ' $1,513.00 ', '1/12/2014', '',],
                    ['A', '70', 'b', '', ' $921.00 ', '1/3/2014', '',],
                    ['', '', '', '', ' $2,518.00 ', '1/6/2014', '',],
                    ['', '', '', '', '', '', '',],
                    ['', '', '', '', ' $1,545.00 ', '', '',],
                ],
            ],
            'table-offset' => [
                FixturesCatalog::FILE_TABLE_OFFSET,
                0,
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
                [
                    ['Government', '', 'Canada', '', '6', 'Carretera', 'None', '1618.5', '', '',],
                    ['Government', '', 'Germany', '', '7', 'Carretera', 'None', '1321', '', '',],
                    ['Midmarket', '', 'France', '', '8', 'Carretera', 'None', '2178', '', 'x',],
                    ['Midmarket', '', 'Germany', '', '9', 'Carretera', 'None', '888', '', 'y',],
                    [
                        'Midmarket', '(empty header)', 'Mexico', '(duplicate header)',
                        '(duplicate header)', 'Carretera', 'None', '2470', '', 'z',
                    ],
                ],
            ],
        ];
    }
}
