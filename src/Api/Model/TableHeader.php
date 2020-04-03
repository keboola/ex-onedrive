<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Model;

use Normalizer;
use function PHPUnit\Framework\assertSame;

class TableHeader implements \JsonSerializable
{
    private string $start;

    private string $end;

    private array $columns;

    public static function from(string $address, array $cells): self
    {
        [$start, $end] = self::parseStartEnd($address);
        // For empty sheet (start = end) API returns first cell, ignore it
        $columns = self::parseColumns($start === $end ? [] : $cells);
        return new self($start, $end, $columns);
    }

    private static function parseStartEnd(string $address): array
    {
        // Eg. address = Sheet1!B1:I123 => start=B, end=I
        // ... or eg. A1 if empty file
        preg_match('~!([A-Z]+)(?:[0-9]+)?(?::([A-Z]+)(?:[0-9]+)?)?$~', $address, $m);
        assert(!empty($m));
        $start = $m[1];
        $end = $m[2] ?? $start;
        return [$start, $end];
    }

    private static function parseColumns(array $columns): array
    {
        $output = [];
        foreach ($columns as $index => $colName) {
            // Normalize column name, fix empty value
            assert(is_string($colName));
            $colName = trim(self::toAscii($colName), " _\t\n\r\0\x0B");
            $colName = empty($colName) ? 'column-' . ($index + 1) : $colName;

            // Prevent duplicates
            $i = 1;
            $orgColName = $colName;
            while (in_array($colName, $output, true)) {
                $colName = "{$orgColName}-$i";
            }

            // Store
            $output[] = $colName;
        }
        return $output;
    }

    public function __construct(string $start, string $end, array $columns)
    {
        $this->start = $start;
        $this->end = $end;
        $this->columns = $columns;
    }

    public function getStart(): string
    {
        return $this->start;
    }

    public function getEnd(): string
    {
        return $this->end;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function jsonSerialize(): array
    {
        return $this->columns;
    }

    private static function toAscii(string $str): string
    {
        $str = Normalizer::normalize($str, Normalizer::FORM_D);
        $str = (string) preg_replace('~\pM~u', '', $str);
        $str = (string) preg_replace('~[^a-zA-Z0-9\-.]+~', '_', $str);
        return $str;
    }
}