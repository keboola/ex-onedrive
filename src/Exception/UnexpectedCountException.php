<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Exception;

use Keboola\CommonExceptions\UserExceptionInterface;

class UnexpectedCountException extends \Exception implements UserExceptionInterface
{

}
