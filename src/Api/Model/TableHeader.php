<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Model;

use Keboola\OneDriveExtractor\Api\Helpers;

class TableHeader extends TableRange implements \JsonSerializable
{
    private array $columns;

    public static function from(string $address, ?array $cells = null): self
    {
        [$start, $end, $firstRowNumber] = self::parseStartEnd($address);

        // For empty sheet (start = end) API returns first cell, ignore it
        $columns = self::parseColumns(!$cells || $start === $end ? [] : $cells);

        // Intentionally 2x firstRowNumber, because header range, not whole table
        return new self($start, $end, $firstRowNumber, $firstRowNumber, $columns);
    }

    public static function parseColumns(array $columns): array
    {
        $output = [];
        foreach ($columns as $index => $colName) {
            // Normalize column name, fix empty value
            $colName = Helpers::toAscii((string) $colName);
            $colName = empty($colName) ? 'column-' . ($index + 1) : $colName;

            // Prevent duplicates
            $i = 1;
            $orgColName = $colName;
            while (in_array($colName, $output, true)) {
                $colName = $orgColName . '-' . $i++;
            }

            // Store
            $output[] = $colName;
        }
        return $output;
    }

    public function __construct(string $start, string $end, int $firstRowNumber, int $lastRowNumber, array $columns)
    {
        parent::__construct($start, $end, $firstRowNumber, $lastRowNumber);
        $this->columns = $columns;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function jsonSerialize(): array
    {
        return $this->columns;
    }
}
