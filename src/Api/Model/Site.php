<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Model;

use InvalidArgumentException;

class Site
{
    private string $id;

    private string $name;

    public static function from(array $data): self
    {
        return new self($data['id'], $data['name']);
    }

    public function __construct(string $id, string $name)
    {
        if (strlen($id) === 0) {
            throw new InvalidArgumentException('Site id cannot be empty.');
        }
        if (strlen($name) === 0) {
            throw new InvalidArgumentException('Site name cannot be empty.');
        }
        $this->id = $id;
        $this->name = $name;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
