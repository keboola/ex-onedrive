<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Tests;

use Keboola\OneDriveExtractor\Api\Helpers;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    private const
        TYPE_FILE_PATH = 'file_path',
        TYPE_DRIVE_FILE_PATH = 'drive_file_path',
        TYPE_SITE_FILE_PATH = 'site_file_path',
        TYPE_HTTPS_URL = 'https_url',
        TYPE_INVALID = 'invalid';

    /**
     * @dataProvider getInputs
     */
    public function testIsFilePath(string $type, string $path): void
    {
        Assert::assertSame($type === self::TYPE_FILE_PATH, Helpers::isFilePath($path));
    }

    /**
     * @dataProvider getInputs
     */
    public function testIsDriveFilePath(string $type, string $path): void
    {
        Assert::assertSame($type === self::TYPE_DRIVE_FILE_PATH, Helpers::isDriveFilePath($path));
    }

    /**
     * @dataProvider getInputs
     */
    public function testIsSiteFilePath(string $type, string $path): void
    {
        Assert::assertSame($type === self::TYPE_SITE_FILE_PATH, Helpers::isSiteFilePath($path));
    }

    /**
     * @dataProvider getInputs
     */
    public function testIsHttpsUrl(string $type, string $path): void
    {
        Assert::assertSame($type === self::TYPE_HTTPS_URL, Helpers::isHttpsUrl($path));
    }

    /**
     * @dataProvider getDriveFilePaths
     */
    public function testExplodeDriveFilePath(array $expected, string $input): void
    {
        Assert::assertSame($expected, Helpers::explodeDriveFilePath($input));
    }

    /**
     * @dataProvider getSiteFilePaths
     */
    public function testExplodeSiteFilePath(array $expected, string $input): void
    {
        Assert::assertSame($expected, Helpers::explodeSiteFilePath($input));
    }


    public function getInputs(): array
    {
        return [
            [self::TYPE_INVALID, ''],
            [self::TYPE_INVALID, '/'],
            [self::TYPE_INVALID, '/foo/'],
            [self::TYPE_INVALID, '/foo/bar/'],
            [self::TYPE_INVALID, '/special_chars/abc123čřž#$%_-/bar/'],
            [self::TYPE_INVALID, 'special_chars/abc123čřž#$%_-/bar/'],
            [self::TYPE_INVALID, 'site://foo'],
            [self::TYPE_INVALID, 'site://foo.xlsx'],
            [self::TYPE_INVALID, 'file'],
            [self::TYPE_INVALID, 'file.xlsx'],
            [self::TYPE_FILE_PATH, '/file'],
            [self::TYPE_FILE_PATH, '/file.xlsx'],
            [self::TYPE_FILE_PATH, '/some/path/file.xlsx'],
            [self::TYPE_FILE_PATH, 'some/path/file.xlsx'],
            [self::TYPE_FILE_PATH, '/some/path1/path2/file.xlsx'],
            [self::TYPE_FILE_PATH, 'some/path1/path2/file.xlsx'],
            [self::TYPE_FILE_PATH, '/some/path1/path2/file.ext1.ext2'],
            [self::TYPE_FILE_PATH, '/dir with space/foo bar/bar'],
            [self::TYPE_FILE_PATH, 'dir with space/foo bar/bar'],
            [self::TYPE_FILE_PATH, '/special_chars/abc123čřž#$%_-/bar'],
            [self::TYPE_FILE_PATH, 'special_chars/abc123čřž#$%_-/bar'],
            [self::TYPE_FILE_PATH, '/special_chars/abc123čřž#$%_-/abc123čřž#$%_-'],
            [self::TYPE_FILE_PATH, 'special_chars/abc123čřž#$%_-/barabc123čřž#$%_-'],
            [self::TYPE_DRIVE_FILE_PATH, 'drive://1234driveId5678/some/path/file'],
            [self::TYPE_DRIVE_FILE_PATH, 'drive://1234driveId5678/ssome/path/file.xlsx'],
            [self::TYPE_DRIVE_FILE_PATH, 'drive://1234driveId5678/ssome/path1/path2/file.xlsx'],
            [self::TYPE_DRIVE_FILE_PATH, 'drive://1234driveId5678/ssome/path1/path2/file.ext1.ext2'],
            [self::TYPE_SITE_FILE_PATH, 'site://site/some/path/file'],
            [self::TYPE_SITE_FILE_PATH, 'site://site/some/path/file.xlsx'],
            [self::TYPE_SITE_FILE_PATH, 'site://site/some/path1/path2/file.xlsx'],
            [self::TYPE_SITE_FILE_PATH, 'site://site/some/path1/path2/file.ext1.ext2'],
            [self::TYPE_SITE_FILE_PATH, 'site://site name with spaces/dir/file'],
            [self::TYPE_SITE_FILE_PATH, 'site://site name with spaces/dir/file.xlsx'],
            [self::TYPE_SITE_FILE_PATH, 'site://special chars abc123čřž#$%_-/dir/file'],
            [self::TYPE_SITE_FILE_PATH, 'site://special chars abc123čřž#$%_-/dir/file.xlsx'],
            [self::TYPE_SITE_FILE_PATH, 'site://special chars abc123čřž#$%_-/abc123 čřž#$%_-'],
            [self::TYPE_SITE_FILE_PATH, 'site://special chars abc123čřž#$%_-/abc123 čřž#$%_-.xlsx'],
            [self::TYPE_HTTPS_URL, 'https://foo'],
            [self::TYPE_HTTPS_URL, 'https://foo.xlsx'],
            [self::TYPE_HTTPS_URL, 'https://some/path2/file.xlsx'],
            [self::TYPE_HTTPS_URL, 'https://some/path1/path2/file.xlsx'],
            [self::TYPE_HTTPS_URL, 'https://some/path1/path2/file.ext1.ext2'],
        ];
    }

    public function getDriveFilePaths(): array
    {
        return [
            [
                ['1234driveId5678', 'some/path/file'],
                'drive://1234driveId5678/some/path/file',
            ],
            [
                ['1234driveId5678', 'path/file.xlsx'],
                'drive://1234driveId5678/path/file.xlsx',
            ],
            [
                ['1234driveId5678', 'path1/path2/file.xlsx'],
                'drive://1234driveId5678/path1/path2/file.xlsx',
            ],
        ];
    }

    public function getSiteFilePaths(): array
    {
        return [
            [
                ['some', 'path/file'],
                'site://some/path/file',
            ],
            [
                ['some', 'path/file.xlsx'],
                'site://some/path/file.xlsx',
            ],
            [
                ['some', 'path1/path2/file.xlsx'],
                'site://some/path1/path2/file.xlsx',
            ],
            [
                ['site name with spaces', 'dir/file'],
                'site://site name with spaces/dir/file',
            ],
            [
                ['site name with spaces', 'dir/file.xlsx'],
                'site://site name with spaces/dir/file.xlsx',
            ],
            [
                ['special chars abc123čřž#$%_-', 'dir/file'],
                'site://special chars abc123čřž#$%_-/dir/file',
            ],
            [
                ['special chars abc123čřž#$%_-', 'dir/file.xlsx'],
                'site://special chars abc123čřž#$%_-/dir/file.xlsx',
            ],
            [
                ['special chars abc123čřž#$%_-', 'abc123 čřž#$%_-'],
                'site://special chars abc123čřž#$%_-/abc123 čřž#$%_-',
            ],
            [
                ['special chars abc123čřž#$%_-', 'abc123 čřž#$%_-.xlsx'],
                'site://special chars abc123čřž#$%_-/abc123 čřž#$%_-.xlsx',
            ],
        ];
    }
}
