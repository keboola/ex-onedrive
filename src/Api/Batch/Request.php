<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Batch;

use Keboola\OneDriveExtractor\Api\Helpers;

class Request
{
    private string $id;

    private string $uri;

    private string $method;

    /** @var callable|null */
    private $responseMapper;

    public function __construct(
        string $id,
        string $uri,
        array $uriArgs,
        ?callable $responseMapper = null,
        string $method = 'GET'
    ) {
        $this->id = $id;
        $this->uri = Helpers::replaceParamsInUri($uri, $uriArgs);
        $this->responseMapper = $responseMapper;
        $this->method = $method;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getResponseMapper(): ?callable
    {
        return $this->responseMapper;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'url' => $this->uri,
        ];
    }
}
