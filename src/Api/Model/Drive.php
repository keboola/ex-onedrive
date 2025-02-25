<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Model;

use InvalidArgumentException;

class Drive
{
    private Site $site;

    private string $id;

    private array $path;

    public static function from(array $data, Site $site): self
    {
        $path = ['sites', $site->getName(), $data['name']];
        return new self($site, $data['id'], $path);
    }

    public function __construct(Site $site, string $id, array $path)
    {
        if ($id === '') {
            throw new InvalidArgumentException('Drive id cannot be empty.');
        }
        $this->site = $site;
        $this->id = $id;
        $this->path = $path;
    }

    public function getSite(): Site
    {
        return $this->site;
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
