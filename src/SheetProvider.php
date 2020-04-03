<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor;

use Keboola\OneDriveExtractor\Api\Api;
use Keboola\OneDriveExtractor\Api\Model\File;
use Keboola\OneDriveExtractor\Configuration\Config;
use Keboola\OneDriveExtractor\Exception\ResourceNotFoundException;

class SheetProvider
{
    private Api $api;

    private Config $config;

    public function __construct(Api $api, Config $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    public function getSheet(): Sheet
    {
        $config = $this->config;
        $workbook = $this->getFile();
        $worksheetId = $config->getWorksheetId();

        if (!$worksheetId) {
            $position = $this->config->getWorksheetPosition();
            assert(is_int($position));
            $worksheetId = $this->getWorksheetIdByPosition($workbook->getDriveId(), $workbook->getFileId(), $position);
        }

        return new Sheet($workbook, $worksheetId);
    }

    public function getFile(): SheetFile
    {
        $config = $this->config;
        $driveId = $config->getDriveId();
        $fileId = $config->getFileId();

        if (!$driveId || !$fileId) {
            $search = $config->getSearch();
            assert(is_string($search));
            [$driveId, $fileId] = $this->searchForFile($search);
        }

        return new SheetFile($driveId, $fileId);
    }

    private function searchForFile(string $search): array
    {
        /** @var File[] $files */
        $files = iterator_to_array($this->api->searchWorkbooks($search));
        $count = count($files);

        // Check number of results
        if ($count === 0) {
            throw new ResourceNotFoundException(sprintf('No file found when searching for "%s".', $search));
        } elseif ($count > 1) {
            $msg = 'Multiple files "%s" found when searching for "%s". Please use a more specific expression.';
            $fileNames = implode('", "', array_map(fn(File $file) => $file->getName(), $files));
            throw new ResourceNotFoundException(sprintf($msg, $fileNames, $search));
        }

        $file = $files[0];
        return [$file->getDriveId(), $file->getFileId()];
    }

    private function getWorksheetIdByPosition(string $driveId, string $fileId, int $position): string
    {
        return $this->api->getWorksheetId($driveId, $fileId, $position);
    }
}
