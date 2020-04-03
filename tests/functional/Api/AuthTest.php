<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\FunctionalTests\Api;

use Keboola\OneDriveExtractor\Exception\AccessTokenInitException;
use Keboola\OneDriveExtractor\Exception\AccessTokenRefreshException;
use PHPUnit\Framework\Assert;

class AuthTest extends BaseTest
{
    /**
     * @dataProvider getValidCredentials`
     */
    public function testValidCredentials(
        string $appId,
        string $appSecret,
        string $accessToken,
        string $refreshToken
    ): void {
        $api = $this->apiFactory->create(
            $appId,
            $appSecret,
            ['access_token' => $accessToken, 'refresh_token' => $refreshToken]
        );
        Assert::assertNotEmpty($api->getAccountName());
    }

    /**
     * @dataProvider getInvalidCredentials
     */
    public function testInvalidCredentials(
        string $expectedExceptionMsg,
        string $appId,
        string $appSecret,
        string $accessToken,
        string $refreshToken
    ): void {
        $this->expectException(AccessTokenRefreshException::class);
        $this->expectExceptionMessage($expectedExceptionMsg);
        $this->apiFactory->create(
            $appId,
            $appSecret,
            ['access_token' => $accessToken, 'refresh_token' => $refreshToken]
        );
    }

    /**
     * @dataProvider getInvalidAuthData
     */
    public function testInvalidAuthDataFormat(
        string $expectedExceptionMsg,
        array $data
    ): void {
        // Try auth with invalid app-id
        $this->expectException(AccessTokenInitException::class);
        $this->expectExceptionMessage($expectedExceptionMsg);
        $appId = (string) getenv('OAUTH_APP_ID');
        $appSecret = (string) getenv('OAUTH_APP_SECRET');
        $this->apiFactory->create($appId, $appSecret, $data);
    }

    public function getValidCredentials(): array
    {
        return [
            'all-valid' => [
                getenv('OAUTH_APP_ID'),
                getenv('OAUTH_APP_SECRET'),
                getenv('OAUTH_ACCESS_TOKEN'),
                getenv('OAUTH_REFRESH_TOKEN'),
            ],
            // Invalid access token is not problem.
            // New access token will be obtained using refresh token.
            'invalid-access-token' => [
                getenv('OAUTH_APP_ID'),
                getenv('OAUTH_APP_SECRET'),
                'invalid-access-token',
                getenv('OAUTH_REFRESH_TOKEN'),
            ],
        ];
    }

    public function getInvalidCredentials(): array
    {
        return [
            'invalid-app-id' => [
                'Microsoft OAuth API token refresh failed, ' .
                'please reset authorization in the extractor configuration.',
                'invalid-app-id',
                getenv('OAUTH_APP_SECRET'),
                getenv('OAUTH_ACCESS_TOKEN'),
                getenv('OAUTH_REFRESH_TOKEN'),
            ],
            'invalid-app-secret' => [
                'Microsoft OAuth API token refresh failed, ' .
                'please reset authorization in the extractor configuration.',
                getenv('OAUTH_APP_ID'),
                'invalid-app-secret',
                getenv('OAUTH_ACCESS_TOKEN'),
                getenv('OAUTH_REFRESH_TOKEN'),
            ],
            'invalid-refresh-token' => [
                'Microsoft OAuth API token refresh failed, ' .
                'please reset authorization in the extractor configuration.',
                getenv('OAUTH_APP_ID'),
                getenv('OAUTH_APP_SECRET'),
                getenv('OAUTH_ACCESS_TOKEN'),
                'invalid-refresh-token',
            ],
        ];
    }

    public function getInvalidAuthData(): array
    {
        return [
            'empty-data' => [
                'Missing key "access_token", "refresh_token" in OAuth data array.',
                [],
            ],
            'missing-access-token' => [
                'Missing key "access_token" in OAuth data array.',
                [
                    'refresh_token' => getenv('OAUTH_REFRESH_TOKEN'),
                ],
            ],
            'missing-refresh-token' => [
                'Missing key "refresh_token" in OAuth data array.',
                [
                    'access_token' => getenv('OAUTH_ACCESS_TOKEN'),
                ],
            ],
        ];
    }
}
