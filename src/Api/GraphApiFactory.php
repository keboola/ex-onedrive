<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api;

use Keboola\OneDriveExtractor\Exception\AccessTokenInitException;
use Keboola\OneDriveExtractor\Exception\AccessTokenRefreshException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Microsoft\Graph\Graph;

class GraphApiFactory
{
    private const
        AUTHORITY_URL = 'https://login.microsoftonline.com/common',
        AUTHORIZE_ENDPOINT = '/oauth2/v2.0/authorize',
        TOKEN_ENDPOINT = '/oauth2/v2.0/token',
        SCOPES = ['offline_access', 'User.Read', 'Files.Read.All', 'Sites.Read.All'];

    public function create(string $appId, string $appSecret, array $authData): Graph
    {
        $token = $this->createOAuthToken($appId, $appSecret, $authData);
        $api = new Graph();
        $api->setAccessToken($token->getToken());
        return $api;
    }

    private function createOAuthToken(string $appId, string $appSecret, array $authData): AccessTokenInterface
    {
        $provider = $this->createOAuthProvider($appId, $appSecret);

        // Check required keys
        $missingKeys = array_diff(['access_token', 'refresh_token'], array_keys($authData));
        if ($missingKeys) {
            throw new AccessTokenInitException(
                sprintf('Missing key "%s" in OAuth data array.', implode('", "', $missingKeys))
            );
        }

        // It is needed to always refresh token, because original token expires after 1 hour
        try {
            $token = new AccessToken($authData);
            return $provider->getAccessToken('refresh_token', ['refresh_token' => $token->getRefreshToken()]);
        } catch (IdentityProviderException $e) {
            throw new AccessTokenRefreshException(
                'Microsoft OAuth API token refresh failed, ' .
                'please reset authorization in the extractor configuration.',
                0,
                $e
            );
        }
    }

    private function createOAuthProvider(string $appId, string $appSecret): GenericProvider
    {
        return new GenericProvider([
            'clientId' => $appId,
            'clientSecret' => $appSecret,
            'redirectUri' => '',
            'urlAuthorize' => self::AUTHORITY_URL . self::AUTHORIZE_ENDPOINT,
            'urlAccessToken' => self::AUTHORITY_URL . self::TOKEN_ENDPOINT,
            'urlResourceOwnerDetails' => '',
            'scopes' => implode(' ', self::SCOPES),
        ]);
    }
}
