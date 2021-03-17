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

    /**
     * @dataProvider getReplaceParamsInUriInputs
     */
    public function testReplaceParamsInUri(string $uri, array $params, string $expectedUri): void
    {
        Assert::assertSame($expectedUri, Helpers::replaceParamsInUri($uri, $params));
    }

    /**
     * @dataProvider getApiPathInputs
     */
    public function testConvertPathToApiFormat(string $input, string $expected): void
    {
        Assert::assertSame($expected, Helpers::convertPathToApiFormat($input));
    }

    /**
     * @dataProvider getToAsciiInputs
     */
    public function testToAscii(string $intput, string $expected): void
    {
        Assert::assertSame($expected, Helpers::toAscii($intput));
    }

    /**
     * @dataProvider getStringsForTruncate
     */
    public function testTruncate(string $intput, int $maxLength, string $expected): void
    {
        Assert::assertSame($expected, Helpers::truncate($intput, $maxLength));
    }

    /**
     * @dataProvider getIterables
     */
    public function testFormatIterable(iterable $values, int $maxItems, string $expected): void
    {
        Assert::assertSame($expected, Helpers::formatIterable($values, $maxItems, 10));
    }

    /**
     * @dataProvider getColumns
     */
    public function testColumnStrToInt(int $expected, string $input): void
    {
        Assert::assertSame($expected, Helpers::columnStrToInt($input));
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

    public function getReplaceParamsInUriInputs(): array
    {
        return [
            ['', [], ''],
            ['http://abc', [], 'http://abc'],
            ['http://abc/{foo}', [], 'http://abc/{foo}'],
            ['http://abc/{foo}', ['foo' => 'bar'], 'http://abc/bar'],
            ['http://abc/{a}/{b}?{c}=value', ['a' => 'd', 'b' => 'e', 'c' => 'f'], 'http://abc/d/e?f=value'],
            ['http://abc/{foo}', ['foo' => 'one or more spaces'], 'http://abc/one+or+more+spaces'],
            [
                'http://abc/{foo}',
                ['foo' => 'special/chars123úěš!@#'],
                'http://abc/special%2Fchars123%C3%BA%C4%9B%C5%A1%21%40%23',
            ],
        ];
    }

    public function getApiPathInputs(): array
    {
        return [
            ['', '/'],
            ['/', '/'],
            ['abc', ':/abc:/'],
            ['/abc', ':/abc:/'],
            ['path/to/file.xlsx', ':/path/to/file.xlsx:/'],
            ['/path/to/file.xlsx', ':/path/to/file.xlsx:/'],
        ];
    }

    public function getToAsciiInputs(): array
    {
        return [
            ['', ''],
            ['aBc', 'aBc'],
            ['!@#', ''],
            ['úěš', 'ues'],
            ['指事字', ''],
            ["a\n\tb_xy", 'a_b_xy'],
        ];
    }

    public function getStringsForTruncate(): array
    {
        return [
            ['abc', -5, '...'],
            ['abc', 0, '...'],
            ['abc', 3, 'abc'],
            ['abcd', 3, 'abc...'],
            ['some longer str', 10, 'some longe...'],
            ['some longer str', 20, 'some longer str'],
            ['special123úěš!@#', 12, 'special123úě...'],
            ['special123úěš!@#', 20, 'special123úěš!@#'],
        ];
    }

    public function getIterables(): array
    {
        return [
            [[], -5, ''],
            [[], 0, ''],
            [[], 10, ''],
            [['a', 'b', 'c'], 3, '"a", "b", "c"'],
            [['a', 'b', 'c'], 2, '"a", "b", ...'],
            [['some long string', 'b', 'c'], 3, '"some long ...", "b", "c"'],
            [['a', 'some long string', 'c'], 3, '"a", "some long ...", "c"'],
            [['a', 'b', 'some long string'], 3, '"a", "b", "some long ..."'],
            [['some long string', 'b', 'c'], 2, '"some long ...", "b", ...'],
            [['a', 'some long string', 'c'], 2, '"a", "some long ...", ...'],
            [['a', 'b', 'some long string'], 2, '"a", "b", ...'],

        ];
    }

    public function getColumns(): array
    {
        return [
            [1, 'A'],
            [2, 'B'],
            [3, 'C'],
            [26, 'Z'],
            [27, 'AA'],
            [28, 'AB'],
            [29, 'AC'],
            [52, 'AZ'],
            [53, 'BA'],
            [731, 'ABC'],
        ];
    }
}
