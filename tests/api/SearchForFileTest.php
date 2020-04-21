<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\ApiTests;

use ArrayIterator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\SkippedTestError;
use Keboola\OneDriveExtractor\Api\Api;
use Keboola\OneDriveExtractor\Exception\InvalidFileTypeException;
use Keboola\OneDriveExtractor\Exception\ShareLinkException;
use Keboola\OneDriveExtractor\Fixtures\FixturesCatalog;

class SearchForFileTest extends BaseTest
{
    public function testEmptySearch(): void
    {
        // Assert some files are found.
        // We cannot test it better,because if empty search, OneDrive returns random sample of files
        $files = iterator_to_array($this->api->searchWorkbooks(''));
        Assert::assertGreaterThan(0, count($files));
    }

    public function testSearchByPathInMeDrive1(): void
    {
        $fixture = $this->fixtures->getMeDrive()->getFile(FixturesCatalog::FILE_ONE_SHEET);
        $path = $fixture->getPath();
        Assert::assertSame('/', $path[0]); // path starts with /

        $files = iterator_to_array($this->api->searchWorkbooks($path));
        $file = $files[0];
        Assert::assertSame(1, count($files));
        Assert::assertSame('one_sheet.xlsx', $file->getName());
        Assert::assertSame(['my', '__ex-onedrive-test-folder', 'valid'], $file->getPath());
        Assert::assertSame($fixture->getDriveId(), $file->getDriveId());
        Assert::assertSame($fixture->getFileId(), $file->getFileId());
    }

    public function testSearchByPathInMeDrive2(): void
    {
        $fixture = $this->fixtures->getMeDrive()->getFile(FixturesCatalog::FILE_ONE_SHEET);
        $path = $fixture->getPath();
        $path = ltrim($path, '/');
        Assert::assertNotSame('/', $path[0]); // path NOT starts with /

        $files = iterator_to_array($this->api->searchWorkbooks($path));
        $file = $files[0];
        Assert::assertSame(1, count($files));
        Assert::assertSame('one_sheet.xlsx', $file->getName());
        Assert::assertSame(['my', '__ex-onedrive-test-folder', 'valid'], $file->getPath());
        Assert::assertSame($fixture->getDriveId(), $file->getDriveId());
        Assert::assertSame($fixture->getFileId(), $file->getFileId());
    }

    public function testSearchByPathInMeDriveNotFound(): void
    {
        $files = iterator_to_array($this->api->searchWorkbooks('/file/not/found'));
        Assert::assertSame(0, count($files));
    }

    public function testSearchByPathInMeDriveInvalidFileType(): void
    {
        $this->expectException(InvalidFileTypeException::class);
        $this->expectExceptionMessage(
            'File is not in the "XLSX" Excel format. Mime type: "application/vnd.oasis.opendocument.text"'
        );
        iterator_to_array($this->api->searchWorkbooks(
            $this->fixtures->getMeDrive()->getFile(FixturesCatalog::FILE_ODT)->getPath()
        ));
    }

    public function testSearchByPathInDrive(): void
    {
        $drive = $this->fixtures->getMeDrive();
        $driveId = urlencode($drive->getDriveId());
        $fixture = $drive->getFile(FixturesCatalog::FILE_ONE_SHEET);
        $files = iterator_to_array($this->api->searchWorkbooks("drive://{$driveId}/{$fixture->getPath()}"));
        $file = $files[0];
        Assert::assertSame(1, count($files));
        Assert::assertSame($fixture->getDriveId(), $file->getDriveId());
        Assert::assertSame($fixture->getFileId(), $file->getFileId());
        Assert::assertSame($fixture->getName(), $file->getName());
        Assert::assertSame(
            $fixture->getPath(),
            '/' .implode('/', $file->getPath()) . '/' . $file->getName()
        );
    }

    public function testSearchByPathInDriveNotFound(): void
    {
        $drive = $this->fixtures->getMeDrive();
        $driveId = urlencode($drive->getDriveId());
        $files = iterator_to_array($this->api->searchWorkbooks("drive://{$driveId}/file/not/found"));
        Assert::assertSame(0, count($files));
    }

    public function testSearchByPathInDriveInvalidFileType(): void
    {
        $drive = $this->fixtures->getMeDrive();
        $driveId = urlencode($drive->getDriveId());
        $fixture = $drive->getFile(FixturesCatalog::FILE_ODT);
        $path = "drive://{$driveId}/{$fixture->getPath()}";

        $this->expectException(InvalidFileTypeException::class);
        $this->expectExceptionMessage(
            'File is not in the "XLSX" Excel format. Mime type: "application/vnd.oasis.opendocument.text"'
        );
        iterator_to_array($this->api->searchWorkbooks($path));
    }

    public function testSearchByPathInSite(): void
    {
        $sharePointDrive = $this->fixtures->getSharePointDrive();
        $sharePointSiteName = $this->fixtures->getSharePointSiteName();
        if (!$sharePointDrive || !$sharePointSiteName) {
            throw new SkippedTestError('Skipped: share point drive is not set');
        }

        $siteName = urlencode($sharePointSiteName);
        $fixture = $sharePointDrive->getFile(FixturesCatalog::FILE_ONE_SHEET);
        $files = iterator_to_array($this->api->searchWorkbooks("site://{$siteName}/{$fixture->getPath()}"));
        $file = $files[0];
        Assert::assertSame(1, count($files));
        Assert::assertSame($fixture->getDriveId(), $file->getDriveId());
        Assert::assertSame($fixture->getFileId(), $file->getFileId());
        Assert::assertSame($fixture->getName(), $file->getName());
        Assert::assertSame(
            "sites/{$sharePointSiteName}" . $fixture->getPath(),
            implode('/', $file->getPath()) . '/' . $file->getName()
        );
    }

    public function testSearchByPathInSiteNotFound(): void
    {
        $sharePointDrive = $this->fixtures->getSharePointDrive();
        if (!$sharePointDrive) {
            throw new SkippedTestError('Skipped: share point drive is not set');
        }

        $siteName = $this->fixtures->getSharePointSiteName();
        $files = iterator_to_array($this->api->searchWorkbooks("site://{$siteName}/file/not/found"));
        Assert::assertSame(0, count($files));
    }

    public function testSearchByPathInSiteInvalidFileType(): void
    {
        $sharePointDrive = $this->fixtures->getSharePointDrive();
        if (!$sharePointDrive) {
            throw new SkippedTestError('Skipped: share point drive is not set');
        }

        $siteName = $this->fixtures->getSharePointSiteName();
        $fixture = $sharePointDrive->getFile(FixturesCatalog::FILE_ODT);
        $path = "site://{$siteName}/{$fixture->getPath()}";

        $this->expectException(InvalidFileTypeException::class);
        $this->expectExceptionMessage(
            'File is not in the "XLSX" Excel format. Mime type: "application/vnd.oasis.opendocument.text"'
        );
        iterator_to_array($this->api->searchWorkbooks($path));
    }

    public function testSearchFileNameWithoutExt(): void
    {
        $files = iterator_to_array($this->api->searchWorkbooks('one_sheet'));
        Assert::assertGreaterThan(0, count($files));
        $file = $files[0];
        Assert::assertSame('one_sheet.xlsx', $file->getName());
    }

    public function testSearchFileNameWithExt(): void
    {
        $files = iterator_to_array($this->api->searchWorkbooks('one_sheet.xlsx'));
        Assert::assertGreaterThan(0, count($files));
        $file = $files[0];
        Assert::assertSame('one_sheet.xlsx', $file->getName());
    }

    public function testSearchByUrlMeDrive(): void
    {
        $fixture = $this->fixtures->getMeDrive()->getFile(FixturesCatalog::FILE_ONE_SHEET);
        $files = iterator_to_array($this->api->searchWorkbooks($fixture->getSharingLink()));
        Assert::assertSame(1, count($files));
        Assert::assertSame('one_sheet.xlsx', $files[0]->getName());
    }

    public function testSearchByUrlSharePoint(): void
    {
        $sharePointDrive = $this->fixtures->getSharePointDrive();
        if (!$sharePointDrive) {
            throw new SkippedTestError('Skipped: share point drive is not set');
        }

        $fixture = $this->fixtures->getMeDrive()->getFile(FixturesCatalog::FILE_ONE_SHEET);
        $files = iterator_to_array($this->api->searchWorkbooks($fixture->getSharingLink()));
        Assert::assertSame(1, count($files));
        Assert::assertSame('one_sheet.xlsx', $files[0]->getName());
    }

    public function testSearchByUrlInvalidFileTypeMeDrive(): void
    {
        $sharePointDrive = $this->fixtures->getSharePointDrive();
        if (!$sharePointDrive) {
            throw new SkippedTestError('Skipped: share point drive is not set');
        }

        $this->expectException(InvalidFileTypeException::class);
        $this->expectExceptionMessage(
            'File is not in the "XLSX" Excel format. Mime type: "application/vnd.oasis.opendocument.text"'
        );
        $fixture = $sharePointDrive->getFile(FixturesCatalog::FILE_ODT);
        iterator_to_array($this->api->searchWorkbooks($fixture->getSharingLink()));
    }

    public function testSearchByUrlInvalidFileTypeSharePoint(): void
    {
        $sharePointDrive = $this->fixtures->getSharePointDrive();
        if (!$sharePointDrive) {
            throw new SkippedTestError('Skipped: share point drive is not set');
        }

        $this->expectException(InvalidFileTypeException::class);
        $this->expectExceptionMessage(
            'File is not in the "XLSX" Excel format. Mime type: "application/vnd.oasis.opendocument.text"'
        );
        $fixture = $sharePointDrive->getFile(FixturesCatalog::FILE_ODT);
        iterator_to_array($this->api->searchWorkbooks($fixture->getSharingLink()));
    }

    public function testSearchByInvalidUrl(): void
    {
        $notExistsUrl = 'https://keboolads.sharepoint.com/:x:/r/sites/KeboolaExtraction/Excel/invalid';
        $this->expectException(ShareLinkException::class);
        $this->expectExceptionMessageMatches(
            '~The sharing link ".*" no exists, or you do not have permission to access it\.~',
        );
        iterator_to_array($this->api->searchWorkbooks($notExistsUrl));
    }

    public function testSearchByTextNoSharePointSitePresent(): void
    {
        // Test for bug COM-214, when no share point site is present,
        // ... and search results to "BadRequest: Invalid batch payload format."
        // In testing account are sharePoint site present, so mock it
        $mock = $this
            ->getMockBuilder(Api::class)
            ->setConstructorArgs([$this->createGraphApi(), $this->logger])
            ->setMethods(['getSites'])
            ->getMock();
        $mock->method('getSites')->willReturn(new ArrayIterator([])); // no site

        /** @var Api $api */
        $api = $mock;
        $files = iterator_to_array($api->searchWorkbooks('file_not_found_abc.xlsx'));
        Assert::assertCount(0, $files);
    }
}
