<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Configuration;

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

    public function getDriveId(): ?string
    {
        return $this->getValue(['parameters', 'workbook', 'driveId'], '') ?: null;
    }

    public function getFileId(): ?string
    {
        return $this->getValue(['parameters', 'workbook', 'fileId'], '') ?: null;
    }

    public function getSearch(): ?string
    {
        return $this->getValue(['parameters', 'workbook', 'search'], '') ?: null;
    }

    public function getWorksheetId(): ?string
    {
        return $this->getValue(['parameters', 'worksheet', 'id'], '') ?: null;
    }

    public function getWorksheetPosition(): ?int
    {
        $value = $this->getValue(['parameters', 'worksheet', 'position'], '');
        // Zero is valid value
        return $value === '' ? null : $value;
    }

    public function getOutputBucket(): ?string
    {
        return $this->getValue(['parameters', 'output', 'bucket'], '') ?: null;
    }

    public function getOutputTable(): string
    {
        return $this->getValue(['parameters', 'output', 'table']);
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

    private function customValidation(): void
    {
        // Missing OAuth data
        if (!$this->getOAuthApiAppKey() || !$this->getOAuthApiAppSecret() || !$this->getOAuthApiData()) {
            throw new InvalidConfigException(
                'Missing OAuth credentials, ' .
                'please set "authorization.oauth_api.credentials.{appKey,#appSecret,#data}".'
            );
        }

        // In config must be present "workbook.search" OR ("workbook,driveId" and "workbook.fileId") - not both
        if ($this->getSearch()) {
            if ($this->getDriveId() || $this->getFileId()) {
                throw new InvalidConfigException(
                    'In config is present "workbook.search", ' .
                    'therefore "workbook,driveId" and "workbook.fileId" are not expected.'
                );
            }
        } elseif ($this->getDriveId() || $this->getFileId()) {
            // If one set, check both are set
            if (!$this->getDriveId() || !$this->getFileId()) {
                throw new InvalidConfigException(
                    'Both "workbook.driveId" and "workbook.fileId" must be configured.'
                );
            }
        } else {
            throw new InvalidConfigException(
                'In config must be present "workbook.search" OR ("workbook.driveId" and "workbook.fileId").'
            );
        }

        // Worksheet definition is required for run action
        if ($this->getAction() === 'run') {
            // In config must be ONY ONE OF "worksheet.id" OR "worksheet.position" - not both
            if ($this->getWorksheetId() && $this->getWorksheetPosition() !== null) {
                throw new InvalidConfigException(
                    'In config must be ONLY ONE OF "worksheet.id" OR "worksheet.position". Both given.'
                );
            } elseif (!$this->getWorksheetId() && $this->getWorksheetPosition() === null) {
                throw new InvalidConfigException(
                    'In config must be ONE OF "worksheet.id" OR "worksheet.position".'
                );
            }
        }
    }
}
