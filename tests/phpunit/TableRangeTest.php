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
     * @dataProvider getUserInputValid
     */
    public function testFromUserInputSuccess(string $input, string $expectedAddress): void
    {
        Assert::assertSame($expectedAddress, TableRange::fromUserInput($input)->getAddress());
    }

    /**
     * @dataProvider getUserInputInvalid
     */
    public function testFromUserInputFail(string $input, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);
        TableRange::fromUserInput($input);
    }

    /**
     * @dataProvider getIntersectData
     */
    public function testIntersect(string $a, string $b, ?string $expected): void
    {
        $result = TableRange::from($a)->intersect(TableRange::from($b));
        Assert::assertSame($expected, $result ? $result->getAddress() : null);
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

    public function getUserInputValid(): iterable
    {
        yield 'simple' => ['B5:Z1000', 'B5:Z1000'];
        yield 'single-cell' => ['A10', 'A10:A10'];
        yield 'lowercase' => ['b5:z1000', 'B5:Z1000'];
        yield 'whitespace' => ["  B5:Z1000\n", 'B5:Z1000'];
        yield 'multi-letter-column' => ['AA5:AB10', 'AA5:AB10'];
        yield 'reversed-columns' => ['Z5:B1000', 'B5:Z1000'];
        yield 'reversed-rows' => ['B1000:Z5', 'B5:Z1000'];
        yield 'last-cell-of-sheet' => ['A1:XFD1048576', 'A1:XFD1048576'];
    }

    public function getUserInputInvalid(): iterable
    {
        yield 'empty' => ['', 'Range cannot be empty.'];
        yield 'whitespace-only' => ['   ', 'Range cannot be empty.'];
        yield 'garbage' => ['foo bar', 'Invalid range "foo bar". Expected A1 notation with both ends bounded'];
        // Would be silently accepted by TableRange::from(), which is why fromUserInput() exists
        yield 'letters-only' => ['GARBAGE', 'Invalid range "GARBAGE". Expected A1 notation with both ends bounded'];
        yield 'whole-columns' => ['B:Z', 'Invalid range "B:Z". Expected A1 notation with both ends bounded'];
        yield 'unbounded-end' => ['B5:Z', 'Invalid range "B5:Z". Expected A1 notation with both ends bounded'];
        yield 'unbounded-start' => ['B:Z1000', 'Invalid range "B:Z1000". Expected A1 notation with both ends bounded'];
        yield 'zero-row' => ['A0:B5', 'Invalid range "A0:B5". Expected A1 notation with both ends bounded'];
        yield 'row-only' => ['5:1000', 'Invalid range "5:1000". Expected A1 notation with both ends bounded'];
        yield 'three-part' => ['A1:B2:C3', 'Invalid range "A1:B2:C3". Expected A1 notation with both ends bounded'];
        yield 'sheet-prefix' => [
            'Sheet1!B5:Z1000',
            'Invalid range "Sheet1!B5:Z1000". The range must not contain a sheet name',
        ];
        yield 'column-out-of-sheet' => [
            'A1:XFE10',
            'Invalid range "A1:XFE10". Column "XFE" is out of the worksheet, the last column is "XFD".',
        ];
        yield 'row-out-of-sheet' => [
            'A1:B1048577',
            'Invalid range "A1:B1048577". Row 1048577 is out of the worksheet, the last row is 1048576.',
        ];
    }

    public function getIntersectData(): iterable
    {
        yield 'identical' => ['C9:L14', 'C9:L14', 'C9:L14'];
        yield 'inside' => ['E9:J14', 'C9:L14', 'E9:J14'];
        yield 'outside-is-clipped' => ['A1:Z1000', 'C9:L14', 'C9:L14'];
        yield 'generous-rows' => ['C9:L100000', 'C9:L14', 'C9:L14'];
        yield 'generous-columns' => ['A9:ZZ14', 'C9:L14', 'C9:L14'];
        yield 'partial-overlap' => ['A1:E10', 'C9:L14', 'C9:E10'];
        yield 'commutative' => ['C9:L14', 'A1:Z1000', 'C9:L14'];
        yield 'no-row-overlap' => ['C20:L30', 'C9:L14', null];
        yield 'no-column-overlap' => ['A9:B14', 'C9:L14', null];
        yield 'single-cell' => ['D10:D10', 'C9:L14', 'D10:D10'];
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
