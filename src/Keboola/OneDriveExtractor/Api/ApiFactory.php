<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api;

class ApiFactory
{
    public function create(string $appId, string $appSecret, array $authData): Api
    {
        $graphApiFactory = new GraphApiFactory();
        $graphApi = $graphApiFactory->create($appId, $appSecret, $authData);
        return new Api($graphApi);
    }
}
