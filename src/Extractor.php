<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor;

use Keboola\OneDriveExtractor\Configuration\Config;
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

    private Config $config;

    private Api $api;

    private string $outputDir;

    private string $outputTable;

    public function __construct(
        LoggerInterface $logger,
        ManifestManager $manifestManager,
        Config $config,
        Api $api,
        string $dataDir,
        string $outputTable
    ) {
        $this->logger = $logger;
        $this->manifestManager = $manifestManager;
        $this->config = $config;
        $this->api = $api;
        $this->outputDir = $dataDir . '/out/tables';
        $this->outputTable = $outputTable;
    }

    public function extract(Sheet $sheet): void
    {
        $errorWhenEmpty = $this->config->shouldErrorWhenEmpty();

        try {
            $sessionId = $this->api->getWorkbookSessionId(
                $sheet->getDriveId(),
                $sheet->getFileId(),
            );

            $sheetContent = $this->api->getWorksheetContent(
                $sheet->getDriveId(),
                $sheet->getFileId(),
                $sheet->getWorksheetId(),
                $this->config->getRowsLimit(),
                $this->config->getCellPerBulk(),
                $sessionId,
            );
        } catch (SheetEmptyException $e) {
            $message = 'Sheet is empty. Nothing was exported.';
            if ($errorWhenEmpty) {
                throw new SheetEmptyException($message, 0, $e);
            }

            $this->logger->warning($message);
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
