<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor;

use UnexpectedValueException;
use Psr\Log\LoggerInterface;
use Keboola\OneDriveExtractor\Api\Api;
use Keboola\OneDriveExtractor\Api\ApiFactory;
use Keboola\Component\BaseComponent;
use Keboola\OneDriveExtractor\Configuration\Config;
use Keboola\OneDriveExtractor\Configuration\Actions\SearchConfigDefinition;
use Keboola\OneDriveExtractor\Configuration\Actions\GetWorksheetsConfigDefinition;
use Keboola\OneDriveExtractor\Configuration\ConfigDefinition;

class Component extends BaseComponent
{
    private Api $api;

    private SheetProvider $sheetProvider;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $config = $this->getConfig();
        $apiFactory = new ApiFactory();
        $this->api = $apiFactory->create(
            $config->getOAuthApiAppKey(),
            $config->getOAuthApiAppSecret(),
            $config->getOAuthApiData()
        );
        $this->sheetProvider = new SheetProvider($this->api, $this->getConfig());
    }

    public function getConfig(): Config
    {
        $config = parent::getConfig();
        assert($config instanceof Config);
        return $config;
    }

    protected function getSyncActions(): array
    {
        return [
            'search' => 'handleSearchSyncAction',
            'getWorksheets' => 'handleGetWorksheetsSyncAction',
        ];
    }

    protected function run(): void
    {
        $sheet = $this->sheetProvider->getSheet();
        $this->createExtractor()->extract($sheet);
    }

    protected function handleSearchSyncAction(): array
    {
        $search = $this->getConfig()->getSearch();
        assert(is_string($search));
        return [
            'files' => iterator_to_array($this->api->searchWorkbooks($search)),
        ];
    }

    protected function handleGetWorksheetsSyncAction(): array
    {
        $workbook = $this->sheetProvider->getFile();
        $worksheets = iterator_to_array($this->api->getWorksheets($workbook->getDriveId(), $workbook->getFileId()));
        return [
            'worksheets' => $worksheets,
        ];
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string
    {
        $action = $this->getRawConfig()['action'] ?? 'run';
        switch ($action) {
            case 'run':
                return ConfigDefinition::class;
            case 'search':
                return SearchConfigDefinition::class;
            case 'getWorksheets':
                return GetWorksheetsConfigDefinition::class;
            default:
                throw new UnexpectedValueException(sprintf('Unexpected action "%s"', $action));
        }
    }

    private function createExtractor(): Extractor
    {
        $config = $this->getConfig();
        return new Extractor(
            $this->getLogger(),
            $this->getManifestManager(),
            $this->api,
            $this->getDataDir(),
            $config->getOutputBucket(),
            $config->getOutputTable(),
        );
    }
}
