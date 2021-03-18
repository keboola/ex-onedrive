<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Model;

use Iterator;
use InvalidArgumentException;
use Keboola\OneDriveExtractor\Api\Helpers;
use Keboola\OneDriveExtractor\Exception\UnexpectedValueException;

class TableRange
{
    private string $start;

    private string $end;

    private int $firstRowNumber;

    private int $lastRowNumber;

    public static function from(string $address): self
    {
        [$start, $end, $firstRowNumber, $lastRowNumber] = self::parseStartEnd($address);
        return new self($start, $end, $firstRowNumber, $lastRowNumber);
    }

    public static function parseStartEnd(string $address): array
    {
        // Eg. address = Sheet1!B123:I456 => start=B, end=I, row=123-456
        // ... or eg. A1 if empty file
        preg_match('~!?([A-Z]+)([0-9]+)?(?::([A-Z]+)([0-9]+)?)?$~', $address, $m);
        if (empty($m)) {
            throw new InvalidArgumentException(sprintf('Unexpected input: "%s"', $address));
        }

        $start = $m[1];
        $firstRowNumber = (int) $m[2];
        $end = $m[3] ?? $start;
        $lastRowNumber = (int) ($m[4] ?? $m[2]);

        return [$start, $end, $firstRowNumber, $lastRowNumber];
    }

    public function __construct(string $start, string $end, int $firstRowNumber, int $lastRowNumber)
    {
        $this->start = $start;
        $this->end = $end;
        $this->firstRowNumber = $firstRowNumber;
        $this->lastRowNumber = $lastRowNumber;
    }

    public function skipRows(int $skip): ?self
    {
        $firstRow = $this->firstRowNumber + $skip;
        $lastRow = $this->lastRowNumber;
        if ($firstRow > $lastRow) {
            // No rows
            return null;
        }

        return new self($this->start, $this->end, $firstRow, $lastRow);
    }

    public function getStart(): string
    {
        return $this->start;
    }

    public function getStartCell(): string
    {
        return $this->start . $this->firstRowNumber;
    }

    public function getEnd(): string
    {
        return $this->end;
    }

    public function getEndCell(): string
    {
        return $this->end . $this->lastRowNumber;
    }

    public function getAddress(): string
    {
        return $this->getStartCell() . ':' . $this->getEndCell();
    }

    public function getFirstRowNumber(): int
    {
        return $this->firstRowNumber;
    }

    public function getLastRowNumber(): int
    {
        return $this->lastRowNumber;
    }

    public function getColumnsCount(): int
    {
        return Helpers::columnStrToInt($this->getEnd()) - Helpers::columnStrToInt($this->getStart()) + 1;
    }

    public function getRowsCount(): int
    {
        return $this->getLastRowNumber() - $this->getFirstRowNumber() + 1;
    }

    /**
     * @return Iterator|self[]
     */
    public function split(int $cellsPerBulk, ?int $limitRows): Iterator
    {
        $rowsPerBulk = (int) floor($cellsPerBulk / $this->getColumnsCount()) ?: 1;
        $bulkIndex = 0;
        $endRow = min(
            $limitRows ? $this->firstRowNumber + $limitRows - 1 : $this->lastRowNumber,
            $this->lastRowNumber
        );

        while (true) {
            $rangeStartRow = $this->firstRowNumber + ($bulkIndex * $rowsPerBulk);
            $rangeEndRow = $rangeStartRow + $rowsPerBulk -1;

            // Last bulk?
            if ($rangeEndRow > $endRow) {
                $rangeEndRow = $endRow;
            }

            // All done?
            if ($rangeStartRow > $endRow) {
                return;
            }

            // Yield range address
            yield TableRange::from($this->start . $rangeStartRow . ':' . $this->end . $rangeEndRow);
            $bulkIndex++;
        }
    }
}
