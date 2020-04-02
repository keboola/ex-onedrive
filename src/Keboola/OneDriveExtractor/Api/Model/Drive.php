<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Model;

class Drive
{
    private string $id;

    private array $path;

    public static function from(array $data, Site $site): self
    {
        $path = ['sites', $site->getName(), $data['name']];
        return new self($data['id'], $path);
    }

    public function __construct(string $id, array $path)
    {
        $this->id = $id;
        $this->path = $path;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPath(): array
    {
        return $this->path;
    }
}
