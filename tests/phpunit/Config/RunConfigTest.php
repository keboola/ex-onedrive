<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Tests\Config;

use Keboola\OneDriveExtractor\Configuration\Config;
use Keboola\OneDriveExtractor\Configuration\ConfigDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class RunConfigTest extends BaseConfigTest
{
    /**
     * @dataProvider validConfigProvider
     */
    public function testValidConfig(array $config): void
    {
        new Config($config, new ConfigDefinition());
        $this->addToAssertionCount(1); // Assert no error
    }

    /**
     * @dataProvider invalidConfigProvider
     */
    public function testInvalidConfig(string $expectedMsg, array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMsg);
        new Config($config, new ConfigDefinition());
    }

    public function validConfigProvider(): array
    {
        return [
            'valid-search-position' => [
                [
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'search' => '/path/to/file',
                        ],
                        'worksheet' => [
                            'name' => 'sheet-table',
                            'position' => 0,
                        ],
                    ],
                ],
            ],
            'valid-file-id' => [
                [
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'driveId' => '1234abc',
                            'fileId' => '5678def',
                        ],
                        'worksheet' => [
                            'name' => 'sheet-table',
                            'position' => 0,
                        ],
                    ],
                ],
            ],
            'valid-worksheet-id' => [
                [
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'driveId' => '1234abc',
                            'fileId' => '5678def',
                        ],
                        'worksheet' => [
                            'name' => 'sheet-table',
                            'id' => '9012xyz',
                        ],
                    ],
                ],
            ],
            'valid-default-bucket' => [
                [
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'driveId' => '1234abc',
                            'fileId' => '5678def',
                        ],
                        'worksheet' => [
                            'name' => 'sheet-table',
                            'id' => '9012xyz',
                        ],
                    ],
                ],
            ],
            'valid-plus-metadata' => [
                [
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'driveId' => '1234abc',
                            'fileId' => '5678def',
                            'metadata' => [
                                'a' => 1,
                                'b' => 'abc',
                            ],
                        ],
                        'worksheet' => [
                            'name' => 'sheet-table',
                            'id' => '9012xyz',
                            'metadata' => [
                                'a' => 1,
                                'b' => 'abc',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function invalidConfigProvider(): array
    {
        return [
            'empty' => [
                'Missing OAuth credentials, ' .
                'please set "authorization.oauth_api.credentials.{appKey,#appSecret,#data}".',
                [],
            ],
            'missing-authorization' => [
                'Missing OAuth credentials, ' .
                'please set "authorization.oauth_api.credentials.{appKey,#appSecret,#data}".',
                [
                    'parameters' => [
                        'workbook' => [
                            'search' => '/path/to/file',
                        ],
                        'worksheet' => [
                            'name' => 'sheet-table',
                            'position' => 0,
                        ],
                    ],
                ],
            ],
            'missing-worksheet-name' => [
                'The child config "name" under "root.parameters.worksheet" must be configured.',
                [
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'search' => '/path/to/file',
                        ],
                        'worksheet' => [
                            'position' => 0,
                        ],
                    ],
                ],
            ],
            'missing-workbook' => [
                'The child config "workbook" under "root.parameters" must be configured.',
                [
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'worksheet' => [
                            'name' => 'sheet-table',
                            'position' => 0,
                        ],
                    ],
                ],
            ],
            'missing-worksheet' => [
                'The child config "worksheet" under "root.parameters" must be configured.',
                [
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'search' => '/path/to/file',
                        ],
                    ],
                ],
            ],
            'missing-file-id' => [
                'Both "workbook.driveId" and "workbook.fileId" must be configured.',
                [
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'driveId' => '1234abc',
                        ],
                        'worksheet' => [
                            'name' => 'sheet-table',
                            'position' => 0,
                        ],
                    ],
                ],
            ],
            'missing-drive-id' => [
                'Both "workbook.driveId" and "workbook.fileId" must be configured.',
                [
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'fileId' => '1234abc',
                        ],
                        'worksheet' => [
                            'name' => 'sheet-table',
                            'position' => 0,
                        ],
                    ],
                ],
            ],
            'extra-workbook-search-key' => [
                'In config is present "workbook.search", ' .
                'therefore "workbook,driveId" and "workbook.fileId" are not expected.',
                [
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'search' => '/path/to/file',
                            'driveId' => '1234abc',
                            'fileId' => '4567def',
                        ],
                        'worksheet' => [
                            'name' => 'sheet-table',
                            'position' => 0,
                        ],
                    ],
                ],
            ],
            'extra-worksheet-position' => [
                'In config must be ONLY ONE OF "worksheet.id" OR "worksheet.position". Both given.',
                [
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'driveId' => '1234abc',
                            'fileId' => '4567def',
                        ],
                        'worksheet' => [
                            'name' => 'sheet-table',
                            'id' => '901xyz',
                            'position' => 0,
                        ],
                    ],
                ],
            ],
        ];
    }
}
