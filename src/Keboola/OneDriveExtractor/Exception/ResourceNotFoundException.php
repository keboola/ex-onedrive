<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Exception;

use Keboola\CommonExceptions\UserExceptionInterface;

class ResourceNotFoundException extends \Exception implements UserExceptionInterface
{

}
