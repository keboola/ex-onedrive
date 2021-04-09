<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api;

use Psr\Log\LoggerInterface;
use Keboola\OneDriveExtractor\Auth\TokenProvider;

class ApiFactory
{
    private LoggerInterface $logger;

    private TokenProvider $tokenProvider;

    public function __construct(LoggerInterface $logger, TokenProvider $tokenProvider)
    {
        $this->logger = $logger;
        $this->tokenProvider = $tokenProvider;
    }

    public function create(): Api
    {
        $graphApiFactory = new GraphApiFactory();
        $graphApi = $graphApiFactory->create($this->tokenProvider->get());
        return new Api($graphApi, $this->logger);
    }
}
