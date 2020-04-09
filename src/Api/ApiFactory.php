<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api;

use Psr\Log\LoggerInterface;

class ApiFactory
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function create(string $appId, string $appSecret, array $authData): Api
    {
        $graphApiFactory = new GraphApiFactory();
        $graphApi = $graphApiFactory->create($appId, $appSecret, $authData);
        return new Api($graphApi, $this->logger);
    }
}
