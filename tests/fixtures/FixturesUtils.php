<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Fixtures;

use Iterator;
use Throwable;
use RuntimeException;
use GuzzleHttp\Exception\ClientException;
use Microsoft\Graph\Model;
use Symfony\Component\Finder\Finder;

class FixturesUtils
{
    private static bool $logEnabled = true;

    private FixturesApi $api;

    public static function disableLog(): void
    {
        self::$logEnabled = false;
    }

    public function __construct()
    {
        $this->api = new FixturesApi();
    }

    public function getMeDriveId(): string
    {
        $body = $this->api->get('/me/drive?$select=id')->getBody();
        return $body['id'];
    }

    public function getSharePointSiteDriveId(string $siteName): string
    {
        // Load site
        $siteName = urlencode($siteName);
        $body = $this->api->get('/sites?search={siteName}&$select=id,name', ['siteName' => $siteName])->getBody();
        $sites = $body['value'];
        if (count($body['value']) === 0) {
            throw new RuntimeException(sprintf(
                'SharePoint site "%s" not found.',
                $siteName
            ));
        } elseif (count($body['value']) > 1) {
            throw new RuntimeException(sprintf(
                'Multiple SharePoint sites "%s" found when searching for "%s".',
                implode('", "', array_map(fn(array $site) => $site['name'], $sites)),
                $siteName
            ));
        }

        // Load drive id
        $siteId = urlencode($body['value'][0]['id']);
        $body = $this->api->get('/sites/{siteId}/drive?$select=id', ['siteId' => $siteId])->getBody();
        return $body['id'];
    }

    public function uploadRecursive(string $driveId, string $relativePath): Iterator
    {
        // Upload file structure, folders are created automatically
        $finder = new Finder();
        foreach ($finder->files()->in($relativePath)->getIterator() as $item) {
            $localPath = $item->getPathname();
            $relativePath = '/' . $item->getRelativePath();
            $relativePath = $relativePath !== '/' ? $relativePath : '';
            $name = $item->getFilename();

            // API sometimes accidentally returns an error, retry!
            $retry = 3;
            while (true) {
                try {
                    yield from $this->uploadFile($driveId, $localPath, $relativePath, $name);
                    break;
                } catch (Throwable $e) {
                    // Delete file, can be partially uploaded
                    if ($retry === 3) {
                        try {
                            $url = $this->api->pathToUrl($driveId, $relativePath . '/' . $name);
                            $this->api->delete($url);
                        } catch (Throwable $e) {
                            // ignore if file not exits
                        }
                    }

                    if ($retry-- <= 0) {
                        var_dump($e);
                        throw $e;
                    }
                }
            }
        }
    }

    private function uploadFile(string $driveId, string $localPath, string $relativePath, string $name): Iterator
    {
        // The size of each byte range MUST be a multiple of 320 KiB
        // https://docs.microsoft.com/cs-cz/graph/api/driveitem-createuploadsession?view=graph-rest-1.0#upload-bytes-to-the-upload-session
        $uploadFragSize = 3200 * 1024; // 3.2 MiB
        $fileSize = filesize($localPath);
        $path = $relativePath . '/' . $name;
        $url = $this->api->pathToUrl($driveId, $relativePath . '/' . $name);

        // Create upload session
        /** @var Model\UploadSession $uploadSession */
        $uploadSession = $this
            ->api
            ->getGraph()
            ->createRequest('POST', $url . 'createUploadSession')
            ->attachBody(['@microsoft.graph.conflictBehavior'=> 'replace' ])
            ->setReturnType(Model\UploadSession::class)
            ->setTimeout('1000')
            ->execute();
        $uploadUrl = $uploadSession->getUploadUrl();

        // Upload file in parts
        $file = fopen($localPath, 'r');
        if (!$file) {
            throw new RuntimeException(sprintf('Cannot open file "%s".', $localPath));
        }

        FixturesUtils::log(sprintf('"%s" - uploading ...', $path));

        try {
            while (!feof($file)) {
                $start = ftell($file);
                $data = fread($file, $uploadFragSize);
                $end = ftell($file);
                $uploadSession = $this
                    ->api
                    ->getGraph()
                    ->createRequest('PUT', $uploadUrl)
                    ->addHeaders([
                        'Content-Length' => $end - $start,
                        'Content-Range' => sprintf('bytes %d-%d/%d', $start, $end-1, $fileSize),
                    ])
                    ->attachBody($data)
                    ->setReturnType(Model\UploadSession::class)
                    ->setTimeout('1000')
                    ->execute() ?? $uploadSession;
                $uploadUrl = $uploadSession->getUploadUrl() ?? $uploadUrl;
                echo '.';
            }
        } finally {
            fclose($file);
        }

        // Uploaded
        $fileId = $uploadSession->getId();
        FixturesUtils::log(sprintf('"%s" - uploaded', $path));

        // Create sharing link (for search by url tests)
        $linkBody = $this->api
            ->post($url . 'createLink', [], ['type' => 'view', 'scope' => 'organization'])
            ->getBody();
        $sharingLink = $linkBody['link']['webUrl'];
        FixturesUtils::log(sprintf('"%s" - created sharing link', $path));

        // Load worksheets if XLSX file
        $worksheets = iterator_to_array($this->loadWorksheets($path, $driveId, $fileId));
        yield $path => new File($path, $driveId, $fileId, $sharingLink, $worksheets);
    }

    private function loadWorksheets(string $path, string $driveId, string $fileId): Iterator
    {
        if (preg_match('~\.xlsx$~', $path)) {
            $body = $this
                ->api
                ->get(
                    '/drives/{driveId}/items/{fileId}/workbook/worksheets?$select=id,position',
                    ['driveId' => $driveId, 'fileId' => $fileId]
                )
                ->getBody();
            foreach ($body['value'] as $item) {
                yield $item['position'] => $item['id'];
            }
            FixturesUtils::log(sprintf('"%s" - loaded worksheet ids', $path));
        }
    }

    public static function log(string $text): void
    {
        if (self::$logEnabled) {
            echo empty($text) ? "\n" : "FixturesUtils: {$text}\n";
        }
    }
}
