<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Fixtures;

use RuntimeException;

/**
 * This class stores metadata about fixtures stored in OneDrive.
 * Fixtures are uploaded at initialization from bootstrap.php.
 * Then are metadata loaded from file in functional tests.
 */
class FixturesCatalog
{
    public const
        CATALOG_FILE = __DIR__ . '/.fixturesCatalog',
        BASE_DIR = '/__ex-onedrive-test-folder',
        // Valid
        FILE_EMPTY = self::BASE_DIR . '/valid/empty.xlsx',
        FILE_HIDDEN_SHEET = self::BASE_DIR . '/valid/hidden_sheet.xlsx',
        FILE_ONE_SHEET = self::BASE_DIR . '/valid/one_sheet.xlsx',
        FILE_MANY_SHEETS = self::BASE_DIR . '/valid/many_sheets.xlsx',
        FILE_ONLY_HEADER = self::BASE_DIR . '/valid/only_header.xlsx',
        FILE_ONLY_ONE_COLUMN = self::BASE_DIR . '/valid/only_one_column.xlsx',
        FILE_SPECIAL_CASES = self::BASE_DIR . '/valid/special_cases.xlsx',
        FILE_TABLE_OFFSET = self::BASE_DIR . '/valid/table_offset.xlsx',
        FILE_BIG = self::BASE_DIR . '/valid/big.xlsx',
        // Invalid
        FILE_CSV = self::BASE_DIR . '/invalid/csv_type.csv',
        FILE_ODS = self::BASE_DIR . '/invalid/ods_type.ods',
        FILE_ODT = self::BASE_DIR . '/invalid/odt_type.odt',
        FILE_XLS = self::BASE_DIR . '/invalid/xls_type.xls';

    private Drive $meDrive;

    private ?string $sharePointSiteName;

    private ?Drive $sharePointDrive;

    private string $envHash;

    public static function initialize(): void
    {
        // Initialization is slow, run only once
        $envHash = self::generateEnvHash();
        if (file_exists(self::CATALOG_FILE)) {
            $catalog = self::load();
            if ($envHash === $catalog->getEnvHash()) {
                // No changes in environment => initialization is not needed
                return;
            }
        }

        $utils = new FixturesUtils();

        // Info
        FixturesUtils::log('');
        FixturesUtils::log('Uploading fixtures to OneDrive');
        FixturesUtils::log('PLEASE CLOSE ALL OPENED FILES ON OneDrive!!!');

        // Me drive
        $meDriveId = $utils->getMeDriveId();
        FixturesUtils::log('');
        FixturesUtils::log('Uploading fixtures to me Drive:');
        $meDriveFiles = iterator_to_array($utils->uploadRecursive($meDriveId, __DIR__ . '/data'));
        $meDrive = new Drive($meDriveId, $meDriveFiles);

        // Share point drive
        /** @var string|null $sharePointSiteName */
        $sharePointSiteName = getenv('TEST_SHAREPOINT_SITE') ?: null;
        if ($sharePointSiteName) {
            FixturesUtils::log('');
            FixturesUtils::log('Env variable TEST_SHAREPOINT_SITE is set.');
            FixturesUtils::log("Loading drive id for SharePoint site: \"{$sharePointSiteName}\"");
            FixturesUtils::log('Uploading fixtures to SharePoint drive:');
            $sharePointDriveId = $utils->getSharePointSiteDriveId($sharePointSiteName);
            $sharePointDriveFiles = iterator_to_array($utils->uploadRecursive($sharePointDriveId, __DIR__ . '/data'));
            $sharePointDrive = new Drive($sharePointDriveId, $sharePointDriveFiles);
        } else {
            FixturesUtils::log('');
            FixturesUtils::log('Env variable TEST_SHAREPOINT_SITE is not set.');
            FixturesUtils::log('SKIPPED upload of fixtures to SharePoint drive.');
            $sharePointSiteName = null;
            $sharePointDrive = null;
        }

        $catalog = new self($meDrive, $sharePointSiteName, $sharePointDrive, $envHash);
        $catalog->store();
    }

    public static function load(): self
    {
        if (!file_exists(self::CATALOG_FILE)) {
            throw new RuntimeException(
                'FixturesCatalog is not initialized. You should call initialize() from bootstrap.php.'
            );
        }

        $catalog = unserialize((string) file_get_contents(self::CATALOG_FILE));
        assert($catalog instanceof self);
        return $catalog;
    }

    public function store(): void
    {
        file_put_contents(self::CATALOG_FILE, serialize($this));
    }

    protected function __construct(Drive $meDrive, ?string $siteName, ?Drive $siteDrive, string $envHash)
    {
        $this->meDrive = $meDrive;
        $this->sharePointSiteName = $siteName;
        $this->sharePointDrive = $siteDrive;
        $this->envHash = $envHash;
    }

    public function getDrive(): Drive
    {
        return $this->getSharePointDrive() ?? $this->getMeDrive();
    }

    public function getMeDrive(): Drive
    {
        return $this->meDrive;
    }

    public function getMeDriveId(): string
    {
        return $this->meDrive->getDriveId();
    }

    public function getSharePointSiteName(): ?string
    {
        return $this->sharePointSiteName;
    }

    public function getSharePointDrive(): ?Drive
    {
        return $this->sharePointDrive;
    }

    public function getEnvHash(): string
    {
        return $this->envHash;
    }

    private static function generateEnvHash(): string
    {
        $envFingerPrint = getenv('TEST_SHAREPOINT_SITE') ?? ''; // TEST_SHAREPOINT_SITE is optional

        $requiredEnVars = [
            'OAUTH_APP_NAME',
            'OAUTH_APP_ID',
            'OAUTH_APP_SECRET',
            'OAUTH_ACCESS_TOKEN',
            'OAUTH_REFRESH_TOKEN',
        ];

        // Check environment and create env hash
        foreach ($requiredEnVars as $var) {
            $value = getenv($var);
            if (empty($value)) {
                throw new \Exception(sprintf('Missing environment var "%s".', $var));
            }
            $envFingerPrint .= $var . ':' . $value . '|';
        }

        return md5($envFingerPrint);
    }
}
