<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Model;

use Iterator;

class SheetContent
{
    private TableHeader $header;

    private TableRange $range;

    private Iterator $rows;

    public static function from(TableHeader $header, string $address, Iterator $rows): self
    {
        $range = TableRange::from($address);
        return new self($header, $range, $rows);
    }

    public function __construct(TableHeader $header, TableRange $range, Iterator $rows)
    {
        $this->header = $header;
        $this->range = $range;
        $this->rows = $rows;
    }

    public function getHeader(): TableHeader
    {
        return $this->header;
    }

    public function getRows(): Iterator
    {
        return $this->rows;
    }

    public function getRange(): TableRange
    {
        return $this->range;
    }

    public function getAddress(): string
    {
        return $this->range->getAddress();
    }
}
