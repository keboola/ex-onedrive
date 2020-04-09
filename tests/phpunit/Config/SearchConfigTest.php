<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Tests\Config;

use Keboola\OneDriveExtractor\Configuration\Actions\SearchConfigDefinition;
use Keboola\OneDriveExtractor\Configuration\Config;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class SearchConfigTest extends BaseConfigTest
{
    /**
     * @dataProvider validConfigProvider
     */
    public function testValidConfig(array $config): void
    {
        new Config($config, new SearchConfigDefinition());
        $this->addToAssertionCount(1); // Assert no error
    }

    /**
     * @dataProvider invalidConfigProvider
     */
    public function testInvalidConfig(string $expectedMsg, array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMsg);
        new Config($config, new SearchConfigDefinition());
    }

    public function validConfigProvider(): array
    {
        return [
            'valid-search' => [
                [
                    'action' => 'search',
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
                    'action' => 'search',
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [
                            'driveId' => '...',
                            'fileId' => '...',
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
                    'action' => 'search',
                ],
            ],
            'missing-authorization' => [
                'Missing OAuth credentials, ' .
                'please set "authorization.oauth_api.credentials.{appKey,#appSecret,#data}".',
                [
                    'action' => 'search',
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
                    'action' => 'search',
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [

                    ],
                ],
            ],
            'empty-workbook' => [
                'In config must be present "workbook.search" OR ("workbook.driveId" and "workbook.fileId").',
                [
                    'action' => 'search',
                    'authorization' => $this->getValidAuthorization(),
                    'parameters' => [
                        'workbook' => [

                        ],
                    ],
                ],
            ],
        ];
    }
}
