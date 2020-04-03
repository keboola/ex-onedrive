<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Model;

use InvalidArgumentException;

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
        if (strlen($id) === 0) {
            throw new InvalidArgumentException('Drive id cannot be empty.');
        }
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
