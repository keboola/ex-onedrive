<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Auth;

use ArrayObject;
use Keboola\OneDriveExtractor\Configuration\Config;
use Psr\Log\LoggerInterface;

class TokenProviderFactory
{
    private Config $config;

    private ArrayObject $stateObject;

    public function __construct(Config $config, ArrayObject $stateObject)
    {
        $this->config = $config;
        $this->stateObject = $stateObject;
    }

    public function create(): TokenProvider
    {
        // OAuth Refresh Token login
        $tokenDataManager = new TokenDataManager($this->config->getOAuthApiData(), $this->stateObject);
        return new RefreshTokenProvider(
            $this->config->getOAuthApiAppKey(),
            $this->config->getOAuthApiAppSecret(),
            $tokenDataManager
        );
    }
}
