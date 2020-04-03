<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\ApiTests;

use Keboola\OneDriveExtractor\Exception\ResourceNotFoundException;
use Keboola\OneDriveExtractor\Exception\UnexpectedValueException;
use Keboola\OneDriveExtractor\Fixtures\FixturesCatalog;
use PHPUnit\Framework\Assert;

class GetSheetIdTest extends BaseTest
{
    /**
     * @dataProvider getFiles
     */
    public function testGetWorksheetId(string $file): void
    {
        $fixture = $this->fixtures->getDrive()->getFile($file);
        $worksheets = $this->api->getWorksheets($fixture->getDriveId(), $fixture->getFileId());
        foreach ($worksheets as $worksheet) {
            Assert::assertSame(
                $worksheet->getWorksheetId(),
                $this->api->getWorksheetId($fixture->getDriveId(), $fixture->getFileId(), $worksheet->getPosition())
            );
        }
    }

    public function testWorksheetNotFound(): void
    {
        $fixture = $this->fixtures->getDrive()->getFile(FixturesCatalog::FILE_ONE_SHEET);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('No worksheet at position "123".');
        $this->api->getWorksheetId($fixture->getDriveId(), $fixture->getFileId(), 123);
    }

    public function testNegativePosition(): void
    {
        $fixture = $this->fixtures->getDrive()->getFile(FixturesCatalog::FILE_ONE_SHEET);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Worksheet position must be greater than zero. Given "-5".');
        $this->api->getWorksheetId($fixture->getDriveId(), $fixture->getFileId(), -5);
    }

    public function getFiles(): array
    {
        return [
            [FixturesCatalog::FILE_ONE_SHEET],
            [FixturesCatalog::FILE_HIDDEN_SHEET],
        ];
    }
}
