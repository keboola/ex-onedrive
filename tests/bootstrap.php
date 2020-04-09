<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Keboola\OneDriveExtractor\Fixtures\FixturesCatalog;
use Keboola\OneDriveExtractor\Fixtures\FixturesUtils;

FixturesCatalog::initialize();
FixturesUtils::disableLog();
