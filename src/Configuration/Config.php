<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Configuration;

use InvalidArgumentException;
use Keboola\Component\Config\BaseConfig;
use Keboola\Component\JsonHelper;
use Keboola\OneDriveExtractor\Exception\InvalidAuthDataException;
use Keboola\OneDriveExtractor\Exception\InvalidConfigException;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Config extends BaseConfig
{
    public function __construct(array $config, ?ConfigurationInterface $configDefinition = null)
    {
        parent::__construct($config, $configDefinition);
        $this->customValidation();
    }

    public function hasDriveId(): bool
    {
        return $this->hasValue(['parameters', 'workbook', 'driveId']);
    }

    public function getDriveId(): string
    {
        return $this->getValue(['parameters', 'workbook', 'driveId']);
    }

    public function hasFileId(): bool
    {
        return $this->hasValue(['parameters', 'workbook', 'fileId']);
    }

    public function getFileId(): string
    {
        return $this->getValue(['parameters', 'workbook', 'fileId']);
    }

    public function hasSearch(): bool
    {
        return $this->hasValue(['parameters', 'workbook', 'search']);
    }

    public function getSearch(): string
    {
        return $this->getValue(['parameters', 'workbook', 'search']);
    }

    public function hasWorksheetId(): bool
    {
        return $this->hasValue(['parameters', 'worksheet', 'id']);
    }

    public function getWorksheetId(): string
    {
        return $this->getValue(['parameters', 'worksheet', 'id']);
    }

    public function hasWorksheetPosition(): bool
    {
        return $this->hasValue(['parameters', 'worksheet', 'position']);
    }


    public function getWorksheetPosition(): int
    {
        return $this->getValue(['parameters', 'worksheet', 'position']);
    }

    public function getWorksheetName(): string
    {
        return $this->getValue(['parameters', 'worksheet', 'name']);
    }

    public function getRowsLimit(): ?int
    {
        return $this->getValue(['parameters', 'rowsLimit']);
    }

    public function getCellPerBulk(): int
    {
        return $this->getValue(['parameters', 'cellsPerBulk']);
    }

    public function shouldErrorWhenEmpty(): bool
    {
        return (bool) $this->getValue(['parameters', 'errorWhenEmpty']);
    }


    public function getOAuthApiData(): array
    {
        $data = parent::getOAuthApiData();

        if (empty($data)) {
            return [];
        }

        if (!is_string($data)) {
            throw new InvalidAuthDataException('Value of "authorization.oauth_api.credentials.#data".');
        }

        try {
            return JsonHelper::decode($data);
        } catch (\Throwable $e) {
            throw new InvalidAuthDataException(sprintf(
                'Value of "authorization.oauth_api.credentials.#data" must be valid JSON, sample: "%s"',
                substr($data, 0, 16)
            ));
        }
    }

    private function hasValue(array $keys): bool
    {
        try {
            $this->getValue($keys);
            return true;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    private function customValidation(): void
    {
        // Missing OAuth data
        if (!$this->getOAuthApiAppKey() || !$this->getOAuthApiAppSecret() || !$this->getOAuthApiData()) {
            throw new InvalidConfigException(
                'Missing OAuth credentials, ' .
                'please set "authorization.oauth_api.credentials.{appKey,#appSecret,#data}".'
            );
        }
    }
}
