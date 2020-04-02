<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\FunctionalTests\Fixtures;

use RuntimeException;

class File
{
    private string $path;

    private string $driveId;

    private string $fileId;

    private string $sharingLink;

    private array $worksheetIds;

    public function __construct(string $path, string $driveId, string $fileId, string $sharingLink, array $worksheetIds)
    {
        assert(strlen($path) > 0);
        assert(strlen($driveId) > 0);
        assert(strlen($fileId) > 0);
        assert(strlen($sharingLink) > 0);
        $this->path = $path;
        $this->driveId = $driveId;
        $this->fileId = $fileId;
        $this->sharingLink = $sharingLink;
        $this->worksheetIds = $worksheetIds;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getName(): string
    {
        return basename($this->path);
    }

    public function getNameWithoutExt(): string
    {
        return pathinfo($this->path, PATHINFO_FILENAME);
    }

    public function getDir(): string
    {
        return dirname($this->path);
    }

    public function getDriveId(): string
    {
        return $this->driveId;
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }

    public function getSharingLink(): string
    {
        return $this->sharingLink;
    }

    public function getWorksheetId(int $position): string
    {
        if (empty($this->worksheetIds)) {
            throw new RuntimeException(sprintf('File "%s" has no worksheets.', $this->getPath()));
        }

        if (!isset($this->worksheetIds[$position])) {
            throw new RuntimeException(sprintf('File "%s" has no worksheet "%d".', $this->getPath(), $position));
        }

        return $this->worksheetIds[$position];
    }
}
