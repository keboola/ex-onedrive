<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Tests;

use InvalidArgumentException;
use Keboola\OneDriveExtractor\Api\Model\TableRow;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class TableRowTest extends TestCase
{
    public function testGetters(): void
    {
        $row = TableRow::from('Sheet1!B123:I456');
        Assert::assertSame('B', $row->getStart());
        Assert::assertSame('B123', $row->getStartCell());
        Assert::assertSame('I', $row->getEnd());
        Assert::assertSame('I456', $row->getEndCell());
        Assert::assertSame(123, $row->getFirstRowNumber());
        Assert::assertSame(456, $row->getLastRowNumber());
    }

    /**
     * @dataProvider getStartsEndsValid
     */
    public function testParseStartEndSuccess(string $input, array $expected): void
    {
        Assert::assertSame($expected, TableRow::parseStartEnd($input));
    }

    /**
     * @dataProvider getStartsEndsInvalid
     */
    public function testParseStartEndFail(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        TableRow::parseStartEnd($input);
    }

    public function getStartsEndsValid(): array
    {
        return [
            [
                'Sheet1!B123:I123',
                ['B', 'I', 123, 123],
            ],
            [
                'Sheet1!B123:I456',
                ['B', 'I', 123, 456],
            ],
            [
                'Sheet1!A10',
                ['A', 'A', 10, 10],
            ],
            [
                'B123:I123',
                ['B', 'I', 123, 123],
            ],
            [
                'B123:I456',
                ['B', 'I', 123, 456],
            ],
            [
                'A10',
                ['A', 'A', 10, 10],
            ],
            [
                'Sheet1 a b c !!!X10:Y20 def ščřšč!B123:I456',
                ['B', 'I', 123, 456],
            ],
            [
                'Sheet1 a b c !!!X10:Y20 def ščřšč!A10',
                ['A', 'A', 10, 10],
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
}
