<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Tests;

use InvalidArgumentException;
use Keboola\OneDriveExtractor\Api\Model\TableHeader;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class TableHeaderTest extends TestCase
{
    /**
     * @dataProvider getStartsEndsValid
     */
    public function testParseStartEndSuccess(string $input, array $expected): void
    {
        Assert::assertSame($expected, TableHeader::parseStartEnd($input));
    }

    /**
     * @dataProvider getStartsEndsInvalid
     */
    public function testParseStartEndFail(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        TableHeader::parseStartEnd($input);
    }

    /**
     * @dataProvider getColumns
     */
    public function testParseColumns(array $input, array $expected): void
    {
        Assert::assertSame($expected, TableHeader::parseColumns($input));
    }

    public function getStartsEndsValid(): array
    {
        return [
            [
                'Sheet1!B1:I123',
                ['B', 'I'],
            ],
            [
                'Sheet1!A10',
                ['A', 'A'],
            ],
            [
                'B1:I123',
                ['B', 'I'],
            ],
            [
                'A10',
                ['A', 'A'],
            ],
            [
                'Sheet1 a b c !!!X10:Y20 def ščřšč!B1:I123',
                ['B', 'I'],
            ],
            [
                'Sheet1 a b c !!!X10:Y20 def ščřšč!A10',
                ['A', 'A'],
            ],
        ];
    }

    public function getStartsEndsInvalid(): array
    {
        return [
            [''],
            ['abc'],
        ];
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
