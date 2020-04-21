<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\ApiTests;

use Keboola\OneDriveExtractor\Api\Api;
use Keboola\OneDriveExtractor\Api\ApiFactory;
use Keboola\OneDriveExtractor\Api\GraphApiFactory;
use Keboola\OneDriveExtractor\Fixtures\FixturesCatalog;
use Microsoft\Graph\Graph;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

abstract class BaseTest extends TestCase
{
    protected TestLogger $logger;

    protected Api $api;

    protected ApiFactory $apiFactory;

    protected FixturesCatalog $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checkEnvironment(['OAUTH_APP_ID', 'OAUTH_APP_SECRET', 'OAUTH_ACCESS_TOKEN', 'OAUTH_REFRESH_TOKEN']);
        $this->logger = new TestLogger();
        $this->apiFactory = new ApiFactory($this->logger);
        $this->api = $this->createApi();
        $this->fixtures = FixturesCatalog::load();
    }

    protected function createApi(): Api
    {
        return $this->apiFactory->create(
            (string) getenv('OAUTH_APP_ID'),
            (string) getenv('OAUTH_APP_SECRET'),
            [
                'access_token' => getenv('OAUTH_ACCESS_TOKEN'),
                'refresh_token' => getenv('OAUTH_REFRESH_TOKEN'),
            ]
        );
    }

    protected function createGraphApi(): Graph
    {
        $graphApiFactory = new GraphApiFactory();
        return $graphApiFactory->create(
            (string) getenv('OAUTH_APP_ID'),
            (string) getenv('OAUTH_APP_SECRET'),
            [
                'access_token' => getenv('OAUTH_ACCESS_TOKEN'),
                'refresh_token' => getenv('OAUTH_REFRESH_TOKEN'),
            ]
        );
    }

    protected function checkEnvironment(array $vars): void
    {
        foreach ($vars as $var) {
            if (empty(getenv($var))) {
                throw new \Exception(sprintf('Missing environment var "%s".', $var));
            }
        }
    }
}
