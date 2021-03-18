<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Tests;

use InvalidArgumentException;
use Keboola\OneDriveExtractor\Api\Model\TableRange;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class TableRangeTest extends TestCase
{
    public function testGetters(): void
    {
        $range = TableRange::from('Sheet1!B123:I456');
        Assert::assertSame('B', $range->getStart());
        Assert::assertSame('B123', $range->getStartCell());
        Assert::assertSame('I', $range->getEnd());
        Assert::assertSame('I456', $range->getEndCell());
        Assert::assertSame(123, $range->getFirstRowNumber());
        Assert::assertSame(456, $range->getLastRowNumber());
    }

    /**
     * @dataProvider getStartsEndsValid
     */
    public function testParseStartEndSuccess(string $input, array $expected): void
    {
        Assert::assertSame($expected, TableRange::parseStartEnd($input));
    }

    /**
     * @dataProvider getStartsEndsInvalid
     */
    public function testParseStartEndFail(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        TableRange::parseStartEnd($input);
    }

    /**
     * @dataProvider getSplitData
     */
    public function testSplit(string $input, int $cellsPerBulk, ?int $limitRows, array $expected): void
    {
        $range = TableRange::from($input);
        $ranges = array_map(
            fn (TableRange $subRange) => $subRange->getAddress(),
            iterator_to_array($range->split($cellsPerBulk, $limitRows))
        );
        Assert::assertSame($expected, $ranges);
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

    public function getSplitData(): iterable
    {
        // Max 1M cells per bulk -> all rows 1 address range
        yield [
            'Sheet1!B123:I456',
            1000000,
            null,
            ['B123:I456'],
        ];

        // Max 2 cells per bulk, but 3 columns in row, -> 1 address range for each row (minimum)
        yield [
            'Sheet1!A123:C125',
            2,
            null,
            ['A123:C123', 'A124:C124', 'A125:C125'],
        ];

        // Max 3 cells per bulk -> 1 address range for each row
        yield [
            'Sheet1!A123:C125',
            3,
            null,
            ['A123:C123', 'A124:C124', 'A125:C125'],
        ];

        // Max 4 cells per bulk -> it is not enough for 2 rows -> 1 address range for each row
        yield [
            'Sheet1!A123:C125',
            3,
            null,
            ['A123:C123', 'A124:C124', 'A125:C125'],
        ];

        // Max 8 cells per bulk -> 2 rows + 2 rows + 1 row
        yield [
            'Sheet1!A123:C127',
            8,
            null,
            ['A123:C124', 'A125:C126', 'A127:C127'],
        ];

        // Limit number of rows
        yield [
            'Sheet1!B123:I456',
            1000000,
            12,
            ['B123:I134'],
        ];
    }
}
