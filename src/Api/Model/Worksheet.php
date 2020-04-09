<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Model;

use InvalidArgumentException;

class Worksheet implements \JsonSerializable
{
    private string $driveId;

    private string $fileId;

    private string $worksheetId;

    private int $position; // 0,1,2 ...

    private string $name;

    private bool $visible;

    private ?TableHeader $header;

    public static function from(array $data, string $driveId, string $fileId): self
    {
        $worksheetId = $data['id'];
        $position = $data['position'];
        $name = $data['name'];
        $visible = strtolower($data['visibility']) === 'visible';
        return new self($driveId, $fileId, $worksheetId, $position, $name, $visible);
    }

    public function __construct(
        string $driveId,
        string $fileId,
        string $worksheetId,
        int $position,
        string $name,
        bool $visible,
        ?TableHeader $header = null
    ) {
        if (strlen($fileId) === 0) {
            throw new InvalidArgumentException('File id cannot be empty.');
        }
        if (strlen($driveId) === 0) {
            throw new InvalidArgumentException('Drive id cannot be empty.');
        }
        if (strlen($worksheetId) === 0) {
            throw new InvalidArgumentException('Worksheet id cannot be empty.');
        }
        if (strlen($name) === 0) {
            throw new InvalidArgumentException('Worksheet name cannot be empty.');
        }

        $this->driveId = $driveId;
        $this->fileId = $fileId;
        $this->worksheetId = $worksheetId;
        $this->position = $position;
        $this->name = $name;
        $this->visible = $visible;
        $this->header = $header;
    }

    public function getDriveId(): string
    {
        return $this->driveId;
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }

    public function getWorksheetId(): string
    {
        return $this->worksheetId;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return "{$this->name}" . ($this->visible ? '' : ' (hidden)');
    }

    public function getHeader(): ?TableHeader
    {
        return $this->header;
    }

    public function setHeader(TableHeader $header): self
    {
        $this->header = $header;
        return $this;
    }

    public function getVisible(): bool
    {
        return $this->visible;
    }

    public function toArray(): array
    {
        return [
            'position' => $this->position,
            'name' => $this->name,
            'title' => $this->getTitle(),
            'driveId' => $this->driveId,
            'fileId' => $this->fileId,
            'worksheetId' => $this->worksheetId,
            'visible' => $this->visible,
            'header' => $this->header,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
