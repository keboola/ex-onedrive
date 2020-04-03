<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Model;

use Iterator;

class SheetContent
{
    private TableHeader $header;

    private Iterator $rows;

    public static function from(TableHeader $header, Iterator $rows): self
    {
        return new self($header, $rows);
    }

    public function __construct(TableHeader $header, Iterator $rows)
    {
        $this->header = $header;
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
}
