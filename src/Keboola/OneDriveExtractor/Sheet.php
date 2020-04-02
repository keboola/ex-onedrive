<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor;

class Sheet
{
    private SheetFile $file;

    private string $worksheetId;

    public function __construct(SheetFile $file, string $worksheetId)
    {
        $this->file = $file;
        $this->worksheetId = $worksheetId;
    }

    public function getDriveId(): string
    {
        return $this->file->getDriveId();
    }

    public function getFileId(): string
    {
        return $this->file->getFileId();
    }

    public function getWorksheetId(): string
    {
        return $this->worksheetId;
    }
}
