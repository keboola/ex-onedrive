<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api;

use Keboola\OneDriveExtractor\Exception\BadRequestException;
use Normalizer;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use Keboola\Component\JsonHelper;
use Keboola\OneDriveExtractor\Exception\InvalidFileTypeException;
use Keboola\OneDriveExtractor\Exception\ResourceNotFoundException;
use Psr\Http\Message\MessageInterface;

class Helpers
{
    public static function isFilePath(string $str): bool
    {
        // Relative or absolute path
        return preg_match('~^(/?[^/]+)?(/[^/]+)+$~ui', $str) === 1;
    }

    public static function isDriveFilePath(string $str): bool
    {
        try {
            self::explodeDriveFilePath($str);
            return true;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    public static function isSiteFilePath(string $str): bool
    {
        try {
            self::explodeSiteFilePath($str);
            return true;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    public static function isHttpsUrl(string $str): bool
    {
        return preg_match('~^https://~', $str) === 1;
    }

    public static function explodeDriveFilePath(string $str): array
    {
        preg_match('~^drive://([^/]+)/(.+)$~', $str, $m);
        if (!$m) {
            throw new InvalidArgumentException('Input not match regexp.');
        }
        $site = urldecode(rtrim($m[1], '/'));
        $path = $m[2];
        return [$site, $path];
    }

    public static function explodeSiteFilePath(string $str): array
    {
        preg_match('~^site://([^/]+)/(.+)$~', $str, $m);
        if (!$m) {
            throw new InvalidArgumentException('Input not match regexp.');
        }
        $site = urldecode(rtrim($m[1], '/'));
        $path = $m[2];
        return [$site, $path];
    }

    public static function processRequestException(RequestException $e): \Throwable
    {
        $error = Helpers::getErrorFromRequestException($e);
        if ($error === 'AccessDenied: Could not obtain a WAC access token.') {
            $msg = 'It looks like the specified file is not in the "XLSX" Excel format. Error: "%s"';
            return new InvalidFileTypeException(sprintf($msg, $error), 0, $e);
        } elseif ($error && strpos($error, 'ItemNotFound:') === 0) {
            return new ResourceNotFoundException('The resource could not be found.', 0, $e);
        } elseif ($error && strpos($error, 'BadRequest: ') === 0) {
            // eg. BadRequest: Tenant does not have a SPO license.
            return new BadRequestException($error, 0, $e);
        }

        return $e;
    }

    public static function getErrorFromRequestException(RequestException $exception): ?string
    {
        try {
            /** @var MessageInterface $response */
            $response = $exception->getResponse();
            $stream = $response->getBody();
            $stream->rewind();
            $body = JsonHelper::decode($stream->getContents());
            $error = $body['error'];
            return sprintf('%s: %s', ucfirst($error['code']), $error['message']);
        } catch (\Throwable $jsonException) {
            return null;
        }
    }

    public static function replaceParamsInUri(string $uri, array $params): string
    {
        // Replace params
        foreach ($params as $key => $value) {
            $uri = str_replace("{{$key}}", urlencode((string) $value), $uri);
        }
        return $uri;
    }

    public static function convertPathToApiFormat(string $path): string
    {
        // API use special path format:
        // eg. root path: /me/drive/root/children ... output of this fn is "/"
        // eg. absolute path /me/drive/root:/path/to/folder:/children ... output of this fn is ":/path/to/folder:/"
        $path = trim($path, '/');
        $path = $path ? (":/{$path}:/") : '/';
        return $path;
    }

    public static function toAscii(string $str): string
    {
        $str = Normalizer::normalize($str, Normalizer::FORM_D);
        $str = (string) preg_replace('~\pM~u', '', $str);
        $str = (string) preg_replace('~[^a-zA-Z0-9\-.]+~', '_', $str);
        $str = trim($str, '_');
        return $str;
    }

    public static function truncate(string $value, int $maxLength = 20): string
    {
        return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength) . '...' : $value;
    }

    public static function formatIterable(iterable $values, int $maxItems = 20, int $strLength = 30): string
    {
        $out = '';
        $i = 0;
        foreach ($values as $value) {
            // Append '...' if there are more items
            if ($i >= $maxItems) {
                $out .= ', ...';
                break;
            }

            // Truncate item length
            $value = self::truncate($value, $strLength);

            $out .= $i === 0 ? "\"{$value}\"" : ", \"{$value}\"";
            $i++;
        }

        return $out;
    }
}
