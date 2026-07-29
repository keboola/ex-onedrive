<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Auth;

use GuzzleHttp\Exception\ConnectException;
use Keboola\OneDriveExtractor\Exception\AccessTokenRefreshException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Retry\BackOff\BackOffPolicyInterface;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\SimpleRetryPolicy;
use Retry\RetryProxy;

class RefreshTokenProvider implements TokenProvider
{
    private const AUTHORITY_URL = 'https://login.microsoftonline.com/common';
    private const AUTHORIZE_ENDPOINT = '/oauth2/v2.0/authorize';
    private const TOKEN_ENDPOINT = '/oauth2/v2.0/token';
    private const SCOPES = ['offline_access', 'User.Read', 'Files.Read.All', 'Sites.Read.All'];

    // The login endpoint sometimes drops the connection (eg. "cURL error 35 ... reset by peer").
    // Such failures are transient, so the request is retried before the job is failed.
    private const RETRY_MAX_ATTEMPTS = 5; // includes the initial try
    private const RETRY_INITIAL_INTERVAL = 1000; // ms, doubled on each attempt
    private const RETRY_EXCEPTIONS = [ConnectException::class];

    private string $appId;

    private string $appSecret;

    private TokenDataManager $dataManager;

    private LoggerInterface $logger;

    public function __construct(
        string $appId,
        string $appSecret,
        TokenDataManager $dataManager,
        ?LoggerInterface $logger = null
    ) {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->dataManager = $dataManager;
        $this->logger = $logger ?? new NullLogger();
    }

    public function get(): AccessTokenInterface
    {
        $provider = $this->createOAuthProvider($this->appId, $this->appSecret);
        $tokens = $this->dataManager->load();

        // It is needed to always refresh token, because original token expires after 1 hour
        $newToken = null;
        $exception = null;

        // Try token from stored state, and from the configuration.
        foreach ($tokens as $token) {
            try {
                $newToken = $this->refreshToken($provider, ['refresh_token' => $token->getRefreshToken()]);
                break;
            } catch (IdentityProviderException $exception) {
                // try next token
            }
        }

        if (!$newToken) {
            throw new AccessTokenRefreshException(
                'Microsoft OAuth API token refresh failed, ' .
                'please reset authorization in the extractor configuration.',
                0,
                $exception
            );
        }

        $this->dataManager->store($newToken);
        return $newToken;
    }

    protected function createOAuthProvider(string $appId, string $appSecret): GenericProvider
    {
        return new GenericProvider([
            'clientId' => $appId,
            'clientSecret' => $appSecret,
            'urlAuthorize' => self::AUTHORITY_URL . self::AUTHORIZE_ENDPOINT,
            'urlAccessToken' => self::AUTHORITY_URL . self::TOKEN_ENDPOINT,
            'urlResourceOwnerDetails' => '',
            'scopes' => implode(' ', self::SCOPES),
        ]);
    }

    /**
     * Separate factory, so tests can replace the back-off with a no-op one.
     */
    protected function createBackOffPolicy(): BackOffPolicyInterface
    {
        return new ExponentialBackOffPolicy(self::RETRY_INITIAL_INTERVAL);
    }

    /**
     * Refreshes the token, retrying only connection level failures.
     *
     * An invalid/expired token still fails on the first try (IdentityProviderException is not
     * retried) and a persistent connection problem is re-thrown after the last attempt,
     * so a failing refresh keeps failing the job.
     */
    private function refreshToken(GenericProvider $provider, array $options): AccessTokenInterface
    {
        $retryProxy = new RetryProxy(
            new SimpleRetryPolicy(self::RETRY_MAX_ATTEMPTS, self::RETRY_EXCEPTIONS),
            $this->createBackOffPolicy(),
            $this->logger
        );

        return $retryProxy->call(function () use ($provider, $options): AccessTokenInterface {
            return $provider->getAccessToken('refresh_token', $options);
        });
    }
}
