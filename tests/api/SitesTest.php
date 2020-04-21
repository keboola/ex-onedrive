<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\ApiTests;

use ArrayIterator;
use PHPUnit\Framework\Assert;
use Keboola\OneDriveExtractor\Api\Api;

class SitesTest extends BaseTest
{
    public function testSearchByTextNoSharePointSitePresent(): void
    {
        // Test for bug COM-214, when no share point site is present,
        // ... and getSitesDrives results to "BadRequest: Invalid batch payload format."
        // In testing account are sharePoint site present, so mock it
        $mock = $this
            ->getMockBuilder(Api::class)
            ->setConstructorArgs([$this->createGraphApi(), $this->logger])
            ->setMethods(['getSites'])
            ->getMock();
        $mock->method('getSites')->willReturn(new ArrayIterator([])); // no site

        /** @var Api $api */
        $api = $mock;
        $sites = iterator_to_array($api->getSitesDrives());
        Assert::assertCount(0, $sites);
    }
}
