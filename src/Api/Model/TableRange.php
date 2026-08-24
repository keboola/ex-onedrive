<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Model;

use Iterator;
use InvalidArgumentException;
use Keboola\OneDriveExtractor\Api\Helpers;
use Keboola\OneDriveExtractor\Exception\UnexpectedValueException;

class TableRange
{
    // Bounds of an Excel worksheet, see https://support.microsoft.com/office/3ac36cb4-f0d5-479d-9e2e-a52c22cbb0a5
    public const
        MAX_COLUMN = 'XFD',
        MAX_ROW = 1048576;

    private string $start;

    private string $end;

    private int $firstRowNumber;

    private int $lastRowNumber;

    public static function from(string $address): self
    {
        [$start, $end, $firstRowNumber, $lastRowNumber] = self::parseStartEnd($address);
        return new self($start, $end, $firstRowNumber, $lastRowNumber);
    }

    /**
     * Creates a range from a user-supplied A1 notation string, eg. "B5:Z1000".
     *
     * Unlike self::from(), which parses a trusted address returned by the Graph API,
     * this method is strict: the input comes from the configuration, so a typo must be
     * reported instead of silently resolving to some other part of the sheet.
     * The returned range is normalized (uppercase, no whitespace, start before end).
     */
    public static function fromUserInput(string $range): self
    {
        $normalized = strtoupper(trim($range));

        if ($normalized === '') {
            throw new InvalidArgumentException('Range cannot be empty.');
        }

        // The worksheet is already selected by "worksheet.id"/"worksheet.position".
        // A sheet name in the range would be ignored, which would silently read a different area.
        if (strpos($normalized, '!') !== false) {
            throw new InvalidArgumentException(sprintf(
                'Invalid range "%s". The range must not contain a sheet name, ' .
                'the worksheet is already selected by "worksheet.id" or "worksheet.position". ' .
                'Expected eg. "B5:Z1000".',
                $range
            ));
        }

        // Both ends must be bounded, eg. "B:Z" or "B5:Z" cannot be converted to a table range
        if (!preg_match('~^([A-Z]{1,3})([1-9][0-9]*)(?::([A-Z]{1,3})([1-9][0-9]*))?$~', $normalized, $m)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid range "%s". Expected A1 notation with both ends bounded, eg. "B5:Z1000".',
                $range
            ));
        }

        $start = $m[1];
        $firstRowNumber = (int) $m[2];
        $end = $m[3] ?? $start;
        $lastRowNumber = isset($m[4]) ? (int) $m[4] : $firstRowNumber;

        foreach ([$start, $end] as $column) {
            if (Helpers::columnStrToInt($column) > Helpers::columnStrToInt(self::MAX_COLUMN)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid range "%s". Column "%s" is out of the worksheet, the last column is "%s".',
                    $range,
                    $column,
                    self::MAX_COLUMN
                ));
            }
        }

        foreach ([$firstRowNumber, $lastRowNumber] as $rowNumber) {
            if ($rowNumber > self::MAX_ROW) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid range "%s". Row %d is out of the worksheet, the last row is %d.',
                    $range,
                    $rowNumber,
                    self::MAX_ROW
                ));
            }
        }

        // Excel reads eg. "Z10:B5" as "B5:Z10", do the same
        if (Helpers::columnStrToInt($start) > Helpers::columnStrToInt($end)) {
            [$start, $end] = [$end, $start];
        }
        if ($firstRowNumber > $lastRowNumber) {
            [$firstRowNumber, $lastRowNumber] = [$lastRowNumber, $firstRowNumber];
        }

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

    /**
     * Returns the overlapping part of the two ranges, or null when they do not overlap.
     */
    public function intersect(self $other): ?self
    {
        $start = Helpers::columnStrToInt($this->start) >= Helpers::columnStrToInt($other->start)
            ? $this->start
            : $other->start;
        $end = Helpers::columnStrToInt($this->end) <= Helpers::columnStrToInt($other->end)
            ? $this->end
            : $other->end;
        $firstRowNumber = max($this->firstRowNumber, $other->firstRowNumber);
        $lastRowNumber = min($this->lastRowNumber, $other->lastRowNumber);

        if (Helpers::columnStrToInt($start) > Helpers::columnStrToInt($end) || $firstRowNumber > $lastRowNumber) {
            return null;
        }

        return new self($start, $end, $firstRowNumber, $lastRowNumber);
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
