<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Tests\Config;

use PHPUnit\Framework\TestCase;

abstract class BaseConfigTest extends TestCase
{
    protected function getValidAuthorization(): array
    {
        return [
            'oauth_api' => [
                'credentials' => [
                    '#data' => '{"access_token": "access", "refresh_token": "refresh"}',
                    '#appSecret' => 'secret',
                    'appKey' => 'key',
                ],
            ],
        ];
    }
}
