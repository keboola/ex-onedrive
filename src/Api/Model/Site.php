<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Model;

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
