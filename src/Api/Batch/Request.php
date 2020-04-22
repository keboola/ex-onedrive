<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api\Batch;

use Keboola\OneDriveExtractor\Api\Helpers;
use Keboola\OneDriveExtractor\Exception\PropertyNotSetException;

class Request
{
    private string $id;

    private string $uri;

    private string $method;

    /** @var callable|null */
    private $responseMapper;

    /** @var callable|null */
    private $exceptionProcessor;

    public function __construct(
        string $id,
        string $uri,
        array $uriArgs,
        ?callable $responseMapper = null,
        ?callable $exceptionProcessor = null,
        string $method = 'GET'
    ) {
        $this->id = $id;
        $this->uri = Helpers::replaceParamsInUri($uri, $uriArgs);
        $this->responseMapper = $responseMapper;
        $this->exceptionProcessor = $exceptionProcessor;
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

    public function hasResponseMapper(): bool
    {
        return $this->responseMapper !== null;
    }

    public function getResponseMapper(): callable
    {
        if (!$this->responseMapper) {
            throw new PropertyNotSetException('Response mapper is not set.');
        }
        return $this->responseMapper;
    }

    public function hasExceptionProcessor(): bool
    {
        return $this->exceptionProcessor !== null;
    }

    public function getExceptionProcessor(): callable
    {
        if (!$this->exceptionProcessor) {
            throw new PropertyNotSetException('Exception processor is not set.');
        }
        return $this->exceptionProcessor;
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
