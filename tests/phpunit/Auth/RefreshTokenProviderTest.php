<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Tests\Auth;

use ArrayObject;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Keboola\OneDriveExtractor\Auth\RefreshTokenProvider;
use Keboola\OneDriveExtractor\Auth\TokenDataManager;
use Keboola\OneDriveExtractor\Exception\AccessTokenRefreshException;
use League\OAuth2\Client\Provider\GenericProvider;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Retry\BackOff\BackOffPolicyInterface;
use Retry\BackOff\NoBackOffPolicy;

class RefreshTokenProviderTest extends TestCase
{
    private const
        APP_ID = 'app-id',
        APP_SECRET = 'app-secret',
        // RefreshTokenProvider::RETRY_MAX_ATTEMPTS, including the initial try
        MAX_ATTEMPTS = 3;

    public function testConnectionErrorIsRetried(): void
    {
        // First attempt is killed by a connection reset, the second one succeeds
        $handler = new MockHandler([
            self::createConnectException(),
            self::createTokenResponse(),
        ]);

        $state = new ArrayObject();
        $token = $this->createProvider($handler, $state)->get();

        Assert::assertSame('new-access-token', $token->getToken());
        Assert::assertSame('new-refresh-token', $token->getRefreshToken());
        Assert::assertCount(0, $handler, 'Both the failed and the successful attempt should be used.');
        Assert::assertArrayHasKey(TokenDataManager::STATE_AUTH_DATA_KEY, $state);
    }

    public function testConnectionErrorIsRethrownAfterLastAttempt(): void
    {
        // The login endpoint is down for good => the job must still fail
        $handler = new MockHandler(array_fill(0, self::MAX_ATTEMPTS, self::createConnectException()));

        try {
            $this->createProvider($handler, new ArrayObject())->get();
            Assert::fail(sprintf('Expected "%s" to be thrown.', ConnectException::class));
        } catch (ConnectException $e) {
            Assert::assertStringContainsString('Connection reset by peer', $e->getMessage());
            Assert::assertCount(0, $handler, 'All attempts should be used, and no more.');
        }
    }

    public function testInvalidTokenIsNotRetried(): void
    {
        // An expired/revoked token is not a connection problem => fail immediately, as before
        $handler = new MockHandler([
            new Response(400, ['Content-Type' => 'application/json'], (string) json_encode([
                'error' => 'invalid_grant',
                'error_description' => 'The refresh token has expired.',
            ])),
        ]);

        $state = new ArrayObject();

        try {
            $this->createProvider($handler, $state)->get();
            Assert::fail(sprintf('Expected "%s" to be thrown.', AccessTokenRefreshException::class));
        } catch (AccessTokenRefreshException $e) {
            Assert::assertStringContainsString('please reset authorization', $e->getMessage());
            Assert::assertCount(0, $handler, 'Only one attempt should be made.');
            Assert::assertArrayNotHasKey(TokenDataManager::STATE_AUTH_DATA_KEY, $state);
        }
    }

    private function createProvider(MockHandler $handler, ArrayObject $state): RefreshTokenProvider
    {
        $dataManager = new TokenDataManager(
            ['access_token' => 'old-access-token', 'refresh_token' => 'old-refresh-token'],
            $state
        );
        $httpClient = new Client(['handler' => HandlerStack::create($handler)]);

        return new class (self::APP_ID, self::APP_SECRET, $dataManager, $httpClient) extends RefreshTokenProvider {
            private ClientInterface $httpClient;

            public function __construct(
                string $appId,
                string $appSecret,
                TokenDataManager $dataManager,
                ClientInterface $httpClient
            ) {
                parent::__construct($appId, $appSecret, $dataManager);
                $this->httpClient = $httpClient;
            }

            protected function createOAuthProvider(string $appId, string $appSecret): GenericProvider
            {
                $provider = parent::createOAuthProvider($appId, $appSecret);
                $provider->setHttpClient($this->httpClient);
                return $provider;
            }

            protected function createBackOffPolicy(): BackOffPolicyInterface
            {
                // Keep the test fast, the real policy waits seconds between the attempts
                return new NoBackOffPolicy();
            }
        };
    }

    private static function createConnectException(): ConnectException
    {
        return new ConnectException(
            'cURL error 35: OpenSSL SSL_connect: Connection reset by peer in connection to ' .
            'login.microsoftonline.com:443',
            new Request('POST', 'https://login.microsoftonline.com/common/oauth2/v2.0/token')
        );
    }

    private static function createTokenResponse(): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]));
    }
}
