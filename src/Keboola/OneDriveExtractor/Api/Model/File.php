<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Model;

class File implements \JsonSerializable
{
    private string $fileId;

    private string $driveId;

    private string $name;

    private array $path;

    public static function from(array $data, array $path = []): self
    {
        $fileId = $data['id'];
        $driveId = $data['parentReference']['driveId'];

        // In response can be defined folder path, eg. /drive/root:/__ex-onedrive-test-folder/valid
        $filePath = $data['parentReference']['path'] ?? null;
        if ($filePath && strpos($filePath, 'root:/') !== false) {
            $parts = explode('root:/', $filePath, 2);
            $path = array_merge($path, explode('/', $parts[1]));
        }

        return new self($fileId, $driveId, $data['name'], $path);
    }

    public function __construct(string $fileId, string $driveId, string $name, array $pathParts)
    {
        $this->fileId = $fileId;
        $this->driveId = $driveId;
        $this->name = $name;
        $this->path = $pathParts;
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }

    public function getDriveId(): string
    {
        return $this->driveId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): array
    {
        return $this->path;
    }

    public function jsonSerialize(): array
    {
        return [
            'driveId' => $this->driveId,
            'fileId' => $this->fileId,
            'name' => $this->name,
            'path' => $this->path ? implode('/', $this->path) : null,
        ];
    }
}
