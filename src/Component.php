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
    public const ACTION_RUN = 'run';
    public const ACTION_SEARCH = 'search';
    public const ACTION_GET_WORKSHEETS = 'getWorksheets';

    private Api $api;

    private SheetProvider $sheetProvider;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $config = $this->getConfig();
        $apiFactory = new ApiFactory($logger);
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
            self::ACTION_SEARCH => 'handleSearchSyncAction',
            self::ACTION_GET_WORKSHEETS => 'handleGetWorksheetsSyncAction',
        ];
    }

    protected function run(): void
    {
        $sheet = $this->sheetProvider->getSheet();
        $this->createExtractor()->extract($sheet);
    }

    protected function handleSearchSyncAction(): array
    {
        return [
            'files' => iterator_to_array($this->api->searchWorkbooks($this->getConfig()->getSearch())),
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
            case self::ACTION_RUN:
                return ConfigDefinition::class;
            case self::ACTION_SEARCH:
                return SearchConfigDefinition::class;
            case self::ACTION_GET_WORKSHEETS:
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
            $this->getConfig(),
            $this->api,
            $this->getDataDir(),
            $config->getWorksheetName(),
        );
    }
}
