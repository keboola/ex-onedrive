<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api;

use League\OAuth2\Client\Token\AccessTokenInterface;
use Microsoft\Graph\Graph;

class GraphApiFactory
{
    public function create(AccessTokenInterface $token): Graph
    {
        $api = new Graph();
        $api->setAccessToken($token->getToken());
        return $api;
    }
}
