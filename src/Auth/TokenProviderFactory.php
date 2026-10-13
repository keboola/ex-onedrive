<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Auth;

use ArrayObject;
use Keboola\OneDriveExtractor\Configuration\Config;
use Psr\Log\LoggerInterface;

class TokenProviderFactory
{
    /**
     * Optional per stack override of the Microsoft identity platform authority,
     * set as "data.image_parameters.oneDriveAuthorityUrl" on the stack.
     * The same key name is used by keboola/wr-onedrive.
     */
    private const AUTHORITY_URL_IMAGE_PARAMETER = 'oneDriveAuthorityUrl';

    private Config $config;

    private ArrayObject $stateObject;

    private ?LoggerInterface $logger;

    public function __construct(Config $config, ArrayObject $stateObject, ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->stateObject = $stateObject;
        $this->logger = $logger;
    }

    public function create(): TokenProvider
    {
        // OAuth Refresh Token login
        $tokenDataManager = new TokenDataManager($this->config->getOAuthApiData(), $this->stateObject);
        return new RefreshTokenProvider(
            $this->config->getOAuthApiAppKey(),
            $this->config->getOAuthApiAppSecret(),
            $this->config->getImageParameters()[self::AUTHORITY_URL_IMAGE_PARAMETER] ?? null,
            $tokenDataManager,
            $this->logger
        );
    }
}
