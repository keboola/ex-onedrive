<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\ApiTests;

use ArrayObject;
use Keboola\Component\JsonHelper;
use Keboola\OneDriveExtractor\Auth\TokenDataManager;
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
        $data = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
        $api = $this->createApi($appId, $appSecret, $data);
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
        $data = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
        $this->expectException(AccessTokenRefreshException::class);
        $this->expectExceptionMessage($expectedExceptionMsg);
        $this->createApi($appId, $appSecret, $data);
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
        $this->createApi($appId, $appSecret, $data);
    }

    public function testEmptyState(): void
    {
        // State is empty
        $state = new ArrayObject();
        $originAccessToken = (string) getenv('OAUTH_ACCESS_TOKEN');
        $originRefreshToken = (string) getenv('OAUTH_REFRESH_TOKEN');

        // Refresh tokens
        $tokenProvider = $this->createRefreshTokenProvider($state);
        $newAccessToken = $tokenProvider->get();

        // We have a new access token
        Assert::assertNotEmpty($newAccessToken->getToken());
        Assert::assertNotSame($originAccessToken, $newAccessToken->getToken());
        Assert::assertNotSame($originRefreshToken, $newAccessToken->getRefreshToken());

        // And tokens are stored to state
        $state = $state->getArrayCopy();
        $dataRaw = $state[TokenDataManager::STATE_AUTH_DATA_KEY];
        $data = JsonHelper::decode($dataRaw);
        Assert::assertNotEmpty($data['access_token']);
        Assert::assertNotEmpty($data['refresh_token']);
        Assert::assertNotSame($originAccessToken, $data['access_token']);
        Assert::assertNotSame($originRefreshToken, $data['refresh_token']);
    }

    public function testEmptyStateInvalidTokens(): void
    {
        $state = new ArrayObject([]);
        $tokenProvider = $this->createRefreshTokenProvider($state, [
            'access_token' => 'invalid',
            'refresh_token' => 'invalid',
        ]);

        $this->expectException(AccessTokenRefreshException::class);
        $this->expectExceptionMessage(
            'Microsoft OAuth API token refresh failed, please reset authorization in the extractor configuration.'
        );
        $tokenProvider->get();
    }

    public function testState(): void
    {
        // State contains valid tokens, from the previous run
        $originAccessToken = (string) getenv('OAUTH_ACCESS_TOKEN');
        $originRefreshToken = (string) getenv('OAUTH_REFRESH_TOKEN');
        $state = new ArrayObject([
            TokenDataManager::STATE_AUTH_DATA_KEY => json_encode([
                'access_token' => $originAccessToken,
                'refresh_token' => $originRefreshToken,
            ]),
        ]);

        // And configuration contains expired old tokens, but they are not used
        $tokenProvider = $this->createRefreshTokenProvider($state, [
            'access_token' => 'old',
            'refresh_token' => 'old',
        ]);
        $newAccessToken = $tokenProvider->get();

        // We have a new access token
        Assert::assertNotEmpty($newAccessToken->getToken());
        Assert::assertNotSame($originAccessToken, $newAccessToken->getToken());
        Assert::assertNotSame($originRefreshToken, $newAccessToken->getRefreshToken());

        // And tokens are stored to state
        $state = $state->getArrayCopy();
        $dataRaw = $state[TokenDataManager::STATE_AUTH_DATA_KEY];
        Assert::assertIsString($dataRaw);
        $data = JsonHelper::decode((string) $dataRaw);
        Assert::assertNotEmpty($data['access_token']);
        Assert::assertNotEmpty($data['refresh_token']);
        Assert::assertNotSame($originAccessToken, $data['access_token']);
        Assert::assertNotSame($originRefreshToken, $data['refresh_token']);
    }

    public function testStateInvalidTokens(): void
    {
        $state = new ArrayObject([
            TokenDataManager::STATE_AUTH_DATA_KEY => json_encode([
                'access_token' => 'invalid',
                'refresh_token' => 'invalid',
            ]),
        ]);
        $tokenProvider = $this->createRefreshTokenProvider($state, [
            'access_token' => 'invalid',
            'refresh_token' => 'invalid',
        ]);

        $this->expectException(AccessTokenRefreshException::class);
        $this->expectExceptionMessage(
            'Microsoft OAuth API token refresh failed, please reset authorization in the extractor configuration.'
        );
        $tokenProvider->get();
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
