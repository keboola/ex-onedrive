<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Tests;

use Keboola\OneDriveExtractor\Api\Model\TableHeader;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class TableHeaderTest extends TestCase
{
    public function testGetters(): void
    {
        $row = TableHeader::from('Sheet1!B123:I456', ['a', 'b', 'b', 'c']);
        Assert::assertSame('B', $row->getStart());
        Assert::assertSame('B123', $row->getStartCell());
        Assert::assertSame('I', $row->getEnd());
        Assert::assertSame('I456', $row->getEndCell());
        Assert::assertSame(123, $row->getFirstRowNumber());
        Assert::assertSame(456, $row->getLastRowNumber());
        Assert::assertSame(['a', 'b', 'b-1', 'c'], $row->getColumns());
    }

    /**
     * @dataProvider getColumns
     */
    public function testParseColumns(array $input, array $expected): void
    {
        Assert::assertSame($expected, TableHeader::parseColumns($input));
    }

    public function getColumns(): array
    {
        return [
            [
                [],
                [],
            ],
            [
                ['', 'b', ''],
                ['column-1', 'b', 'column-3'],
            ],
            [
                ['', 'column-1', '', 'column-3', 'column-1', 'column-3'],
                ['column-1', 'column-1-1', 'column-3', 'column-3-1', 'column-1-2', 'column-3-2'],
            ],
            [
                ['a', 'b', 'c'],
                ['a', 'b', 'c'],
            ],
            [
                ['!@#', 'úěš', '指事字'],
                ['column-1', 'ues', 'column-3'],
            ],
            [
                ['col1', 'col1', 'col1'],
                ['col1', 'col1-1', 'col1-2'],
            ],
        ];
    }
}
