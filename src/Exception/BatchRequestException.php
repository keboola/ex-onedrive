<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Exception;

use Keboola\CommonExceptions\ApplicationExceptionInterface;
use Throwable;

class BatchRequestException extends \Exception implements ApplicationExceptionInterface
{
    private string $originalMessage;

    private array $body;

    public function __construct(
        string $message = '',
        string $originalMessage = '',
        array $body = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->originalMessage = $originalMessage;
        $this->body = $body;
    }

    public function getOriginalMessage(): string
    {
        return $this->originalMessage;
    }

    public function getBody(): array
    {
        return $this->body;
    }
}
