<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api;

use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use Keboola\Component\JsonHelper;
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
}