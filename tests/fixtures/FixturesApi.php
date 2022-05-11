<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Fixtures;

use ArrayObject;
use GuzzleHttp\Exception\RequestException;
use Keboola\OneDriveExtractor\Api\Api;
use Keboola\OneDriveExtractor\Api\GraphApiFactory;
use Keboola\OneDriveExtractor\Api\Helpers;
use Keboola\OneDriveExtractor\Auth\TokenDataManager;
use Keboola\OneDriveExtractor\Exception\BatchRequestException;
use Keboola\OneDriveExtractor\Auth\RefreshTokenProvider;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphResponse;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\CallableRetryPolicy;
use Retry\RetryProxy;

class FixturesApi
{
    private Graph $graphApi;

    public function __construct()
    {
        $this->graphApi = $this->createGraphApi();
    }

    public function getGraph(): Graph
    {
        return $this->graphApi;
    }

    public function get(string $uri, array $params = []): GraphResponse
    {
        return $this->executeWithRetry('GET', $uri, $params);
    }

    public function post(string $uri, array $params = [], array $body = []): GraphResponse
    {
        return $this->executeWithRetry('POST', $uri, $params, $body);
    }

    public function delete(string $uri, array $params = []): GraphResponse
    {
        return $this->executeWithRetry('DELETE', $uri, $params);
    }

    public function executeWithRetry(string $method, string $uri, array $params = [], array $body = []): GraphResponse
    {
        $backOffPolicy = new ExponentialBackOffPolicy(100, 2.0, 2000);
        $retryPolicy = new CallableRetryPolicy(function (\Throwable $e) {
            if ($e instanceof RequestException || $e instanceof BatchRequestException) {
                // Retry only on defined HTTP codes
                if (in_array($e->getCode(), Api::RETRY_HTTP_CODES, true)) {
                    return true;
                }

                // Retry if communication problems
                if (strpos($e->getMessage(), 'There were communication or server problems')) {
                    return true;
                }
            }

            return false;
        });

        $proxy = new RetryProxy($retryPolicy, $backOffPolicy);
        return $proxy->call(function () use ($method, $uri, $params, $body) {
            return $this->execute($method, $uri, $params, $body);
        });
    }

    public function execute(string $method, string $uri, array $params = [], array $body = []): GraphResponse
    {
        $uri = Helpers::replaceParamsInUri($uri, $params);
        $request = $this->graphApi->createRequest($method, $uri);
        if ($body) {
            $request->attachBody($body);
        }

        try {
            return $request->execute();
        } catch (RequestException $e) {
            throw Helpers::processRequestException($e);
        }
    }

    public function pathToUrl(string $driveId, string $path): string
    {
        $driveId = urlencode($driveId);
        $path = Helpers::convertPathToApiFormat($path);
        return "/drives/{$driveId}/root{$path}";
    }

    private function createGraphApi(): Graph
    {
        $appId = (string) getenv('OAUTH_APP_ID');
        $appSecret = (string) getenv('OAUTH_APP_SECRET');
        $accessToken = (string) getenv('OAUTH_ACCESS_TOKEN');
        $refreshToken = (string) getenv('OAUTH_REFRESH_TOKEN');
        $oauthData = [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
            ];
        $dataManager = new TokenDataManager($oauthData, new ArrayObject());
        $tokenProvider = new RefreshTokenProvider($appId, $appSecret, $dataManager);
        $apiFactory = new GraphApiFactory();
        return $apiFactory->create($tokenProvider->get());
    }
}
