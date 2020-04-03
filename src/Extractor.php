<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor;

use Psr\Log\LoggerInterface;
use Keboola\Component\Manifest\ManifestManager;
use Keboola\Component\Manifest\ManifestManager\Options\OutTableManifestOptions;
use Keboola\Csv\CsvWriter;
use Keboola\OneDriveExtractor\Api\Api;
use Keboola\OneDriveExtractor\Exception\SheetEmptyException;

class Extractor
{
    private LoggerInterface $logger;

    private ManifestManager $manifestManager;

    private Api $api;

    private string $outputDir;

    private string $outputTable;

    public function __construct(
        LoggerInterface $logger,
        ManifestManager $manifestManager,
        Api $api,
        string $dataDir,
        string $outputTable
    ) {
        $this->logger = $logger;
        $this->manifestManager = $manifestManager;
        $this->api = $api;
        $this->outputDir = $dataDir . '/out/tables';
        $this->outputTable = $outputTable;
    }

    public function extract(Sheet $sheet): void
    {
        try {
            $sheetContent = $this->api->getWorksheetContent(
                $sheet->getDriveId(),
                $sheet->getFileId(),
                $sheet->getWorksheetId()
            );
        } catch (SheetEmptyException $e) {
            $this->logger->warning('Sheet is empty. Nothing was exported.');
            return;
        }

        // Write rows
        $csvFile = "{$this->outputTable}.csv";
        $csvWriter = new CsvWriter("{$this->outputDir}/${csvFile}");
        foreach ($sheetContent->getRows() as $row) {
            $csvWriter->writeRow($row);
        }

        // Write manifest
        $options = new OutTableManifestOptions();
        $options->setColumns($sheetContent->getHeader()->getColumns());
        $this->manifestManager->writeTableManifest($csvFile, $options);
    }
}
