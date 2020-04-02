<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor;

class SheetFile
{
    private string $driveId;

    private string $fileId;

    public function __construct(string $driveId, string $fileId)
    {
        $this->driveId = $driveId;
        $this->fileId = $fileId;
    }

    public function getDriveId(): string
    {
        return $this->driveId;
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }
}
