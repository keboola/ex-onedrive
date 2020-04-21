<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Exception;

use Keboola\CommonExceptions\ApplicationExceptionInterface;
use Throwable;

class BatchRequestException extends \Exception implements ApplicationExceptionInterface
{
    private string $originalMessage;

    public function __construct(
        string $message = '',
        string $originalMessage = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->originalMessage = $originalMessage;
    }

    public function getOriginalMessage(): string
    {
        return $this->originalMessage;
    }
}
