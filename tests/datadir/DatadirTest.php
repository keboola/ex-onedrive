<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\DataDirTests;

use PHPUnit\Framework\SkippedTestError;
use RuntimeException;
use ReflectionClass;
use Keboola\OneDriveExtractor\Fixtures\FixturesCatalog;
use Keboola\DatadirTests\AbstractDatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecificationInterface;
use Keboola\DatadirTests\DatadirTestsProviderInterface;
use Symfony\Component\Process\Process;

class DatadirTest extends AbstractDatadirTestCase
{
    protected FixturesCatalog $fixtures;

    protected ReflectionClass $fixturesCatalogRef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = FixturesCatalog::load();
        $this->fixturesCatalogRef = new ReflectionClass(FixturesCatalog::class);
    }


    /**
     * @dataProvider provideDatadirSpecifications
     */
    public function testDatadir(DatadirTestSpecificationInterface $specification): void
    {
        $tempDatadir = $this->getTempDatadir($specification);

        // Replace environment variables in config.json
        $configPath = $tempDatadir->getTmpFolder() . '/config.json';
        if (file_exists($configPath)) {
            $configContent = $this->modifyConfigFile((string) file_get_contents($configPath));
            file_put_contents($configPath, $configContent);
        }

        $process = $this->runScript($tempDatadir->getTmpFolder());

        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
    }

    /**
     * @return DatadirTestsProviderInterface[]
     */
    protected function getDataProviders(): array
    {
        return [
            new DatadirTestsProvider($this->getTestFileDir()),
        ];
    }

    protected function modifyConfigFile(string $content): string
    {
        return (string) preg_replace_callback('~\$\{([^{}]+)}~', function (array $m) {
            $var = $m[1];
            $parts = explode('::', $var);

            switch (true) {
                // Special OAUTH_DATA variable
                case $var === 'OAUTH_DATA':
                    return (string) addslashes((string) json_encode([
                        'access_token' => getenv('OAUTH_ACCESS_TOKEN'),
                        'refresh_token' => getenv('OAUTH_REFRESH_TOKEN'),
                    ]));

                case $var === 'FIXTURES_CATALOG::getSharePointSiteName':
                    $sharePointSiteName = $this->fixtures->getSharePointSiteName();
                    if (!$sharePointSiteName) {
                        throw new SkippedTestError('SharePoint environment is not set.');
                    }
                    return $sharePointSiteName;

                case $var === 'FIXTURES_CATALOG::getMeDriveId':
                    return $this->fixtures->getMeDriveId();

                // Get file's property from FixturesCatalog
                case $parts[0] === 'FIXTURES_CATALOG':
                    array_shift($parts);
                    $driveType = (string) array_shift($parts);
                    $fileConst = (string) array_shift($parts);
                    $method = (string) array_shift($parts);

                    // Get drive
                    switch ($driveType) {
                        case 'DRIVE':
                            $drive = $this->fixtures->getDrive();
                            break;

                        case 'ME_DRIVE':
                            $drive = $this->fixtures->getMeDrive();
                            break;

                        case 'SHAREPOINT_DRIVE':
                            $drive = $this->fixtures->getSharePointDrive();
                            break;

                        default:
                            throw new RuntimeException(sprintf('Unexpected drive type ""%s.', $driveType));
                    }

                    if (!$drive) {
                        throw new SkippedTestError('Required type of "drive" is not present.');
                    }

                    // Get file
                    $file = $drive->getFile($this->fixturesCatalogRef->getConstant($fileConst));

                    // Return file property
                    $args = array_map(fn($arg) => is_numeric($arg) ? (int) $arg : $arg, $parts);
                    return $file->{$method}(...$args);

                // Return environment variable
                default:
                    $value = getenv($var);
                    if (!$value) {
                        throw new RuntimeException(sprintf('Environment variable "%s" not found.', $var));
                    }
                    return $value;
            }
        }, $content);
    }

    protected function assertMatchesSpecification(
        DatadirTestSpecificationInterface $specification,
        Process $runProcess,
        string $tempDatadir
    ): void {
        if ($specification->getExpectedReturnCode() !== null) {
            $this->assertProcessReturnCode($specification->getExpectedReturnCode(), $runProcess);
        } else {
            $this->assertNotSame(0, $runProcess->getExitCode(), 'Exit code should have been non-zero');
        }
        if ($specification->getExpectedStdout() !== null) {
            // Match format, not exact same
            $this->assertStringMatchesFormat(
                trim($specification->getExpectedStdout()),
                trim($runProcess->getOutput()),
                'Failed asserting stdout output'
            );
        }
        if ($specification->getExpectedStderr() !== null) {
            // Match format, not exact same
            $this->assertStringMatchesFormat(
                trim($specification->getExpectedStderr()),
                trim($runProcess->getErrorOutput()),
                'Failed asserting stderr output'
            );
        }
        if ($specification->getExpectedOutDirectory() !== null) {
            $this->assertDirectoryContentsSame(
                $specification->getExpectedOutDirectory(),
                $tempDatadir . '/out'
            );
        }
    }

    protected function getScript(): string
    {
        return $this->getTestFileDir() . '/../../src/run.php';
    }
}
