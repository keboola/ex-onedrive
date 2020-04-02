<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\FunctionalTests\Fixtures;

use InvalidArgumentException;

class Drive
{
    private string $driveId;

    private array $files = [];

    public function __construct(string $driveId, array $files)
    {
        assert(strlen($driveId) > 0);
        $this->driveId = $driveId;
        $this->files = $files;
    }

    public function getDriveId(): string
    {
        return $this->driveId;
    }

    public function getFile(string $path): File
    {
        if (isset($this->files[$path])) {
            return $this->files[$path];
        }
        throw new InvalidArgumentException(sprintf('Fixture file "%s" not found.', $path));
    }

    public function addFile(File $file): void
    {
        $this->files[$file->getPath()] = $file;
    }
}
