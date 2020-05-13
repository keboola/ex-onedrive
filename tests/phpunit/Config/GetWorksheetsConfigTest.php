<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Tests\Config;

use Keboola\OneDriveExtractor\Configuration\Actions\GetWorksheetsConfigDefinition;
use Keboola\OneDriveExtractor\Configuration\Config;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class GetWorksheetsConfigTest extends BaseConfigTest
{
    /**
     * @dataProvider validConfigProvider
     */
    public function testValidConfig(array $config): void
    {
        new Config($config, new GetWorksheetsConfigDefinition());
        $this->expectNotToPerformAssertions();
    }

    /**
     * @dataProvider invalidConfigProvider
     */
    public function testInvalidConfig(string $expectedMsg, array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMsg);
        new Config($config, new GetWorksheetsConfigDefinition());
    }

    public function validConfigProvider(): array
    {
        return [
            'valid-search' => [
                [
                    'action' => 'getWorksheets',
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'search' => '/path/to/file',
                        ],
                    ],
                ],
            ],
            'valid-ids' => [
                [
                    'action' => 'getWorksheets',
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'driveId' => '1234abc',
                            'fileId' => '5678def',
                        ],
                    ],
                ],
            ],
            'valid-ids-plus-metadata' => [
                [
                    'action' => 'getWorksheets',
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
                [
                    'action' => 'getWorksheets',
                ],
            ],
            'missing-authorization' => [
                'Missing OAuth credentials, ' .
                'please set "authorization.oauth_api.credentials.{appKey,#appSecret,#data}".',
                [
                    'action' => 'getWorksheets',
                    'parameters' => [
                        'workbook' => [
                            'search' => '/path/to/file',
                        ],
                    ],
                ],
            ],
            'missing-workbook' => [
                'The child node "workbook" at path "root.parameters" must be configured.',
                [
                    'action' => 'getWorksheets',
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [

                    ],
                ],
            ],
            'missing-file-id' => [
                'Both "workbook.driveId" and "workbook.fileId" must be configured.',
                [
                    'action' => 'getWorksheets',
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'driveId' => '1234abc',
                        ],
                    ],
                ],
            ],
            'missing-drive-id' => [
                'Both "workbook.driveId" and "workbook.fileId" must be configured.',
                [
                    'action' => 'getWorksheets',
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'fileId' => '1234abc',
                        ],
                    ],
                ],
            ],
            'extra-workbook-search-key' => [
                'In config is present "workbook.search", ' .
                'therefore "workbook,driveId" and "workbook.fileId" are not expected.',
                [
                    'action' => 'getWorksheets',
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'search' => '/path/to/file',
                            'driveId' => '1234abc',
                            'fileId' => '4567def',
                        ],
                    ],
                ],
            ],
        ];
    }
}
