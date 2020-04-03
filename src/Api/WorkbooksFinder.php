<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api;

use Iterator;
use GuzzleHttp\Exception\RequestException;
use Keboola\OneDriveExtractor\Api\Model\File;
use Keboola\OneDriveExtractor\Exception\InvalidFileTypeException;
use Keboola\OneDriveExtractor\Exception\ResourceNotFoundException;
use Keboola\OneDriveExtractor\Exception\ShareLinkException;
use Psr\Log\LoggerInterface;

class WorkbooksFinder
{
    public const ALLOWED_MIME_TYPES = [
        # Only XLSX files can by accessed through API
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private Api $api;

    private LoggerInterface $logger;

    public function __construct(Api $api, LoggerInterface $logger)
    {
        $this->api = $api;
        $this->logger = $logger;
    }

    /**
     * @return Iterator|File[]
     */
    public function search(string $search): Iterator
    {
        try {
            switch (true) {
                // Drive path, eg. "/path/to/file.xlsx"
                case Helpers::isFilePath($search):
                    $this->log('Searching for "%s" in personal OneDrive.', $search);
                    yield from $this->searchByPathInMeDrive($search);
                    break;

                // Site path, eg. "drive://1234driveId6789/path/to/file.xlsx"
                case Helpers::isDriveFilePath($search):
                    [$driveId, $path] = Helpers::explodeDriveFilePath($search);
                    $this->log(
                        'Searching for "%s" in drive "%s".',
                        $path,
                        Helpers::truncate($driveId, 15)
                    );
                    yield from $this->searchByPathInDrive('/drives/' . urlencode($driveId), $path, []);
                    break;

                // Site path, eg. "site://Excel Sheets/path/to/file.xlsx"
                case Helpers::isSiteFilePath($search):
                    [$siteName, $path] = Helpers::explodeSiteFilePath($search);
                    $this->log('Searching for "%s" in site "%s".', $path, $siteName);
                    yield from $this->searchByPathInSite($siteName, $path);
                    break;

                // Https url, eg: "https://keboolads.sharepoint.com/..."
                case Helpers::isHttpsUrl($search):
                    $this->log('Searching by link "%s".', Helpers::truncate($search, 20));
                    yield from $this->searchByUrl($search);
                    break;

                // Search for file by text in all locations
                default:
                    $this->log('Searching for "%s" in all locations.', $search);
                    yield from $this->searchByText($search);
                    break;
            }
        } catch (ResourceNotFoundException $e) {
            yield from  [];
        }
    }

    /**
     * @return Iterator|File[]
     */
    private function searchByPathInMeDrive(string $path): Iterator
    {
        return $this->searchByPathInDrive('/me/drive', $path, ['my']);
    }

    /**
     * @return Iterator|File[]
     */
    private function searchByPathInSite(string $siteName, string $path): Iterator
    {
        $site = $this->api->getSite($siteName);
        $prefix = '/sites/' . urlencode($site->getId()) .  '/drive';
        return $this->searchByPathInDrive($prefix, $path, ['sites', $siteName]);
    }

    /**
     * @return Iterator|File[]
     */
    private function searchByPathInDrive(string $drivePrefix, string $path, array $pathPrefix): Iterator
    {
        $path = Helpers::convertPathToApiFormat($path);
        $url = "{$drivePrefix}/root{$path}?\$select=id,name,parentReference,file";
        $body = $this->api->get($url)->getBody();

        // Check mime type
        self::checkFileMimeType($body);

        // Convert to object
        yield File::from($body, $pathPrefix);
    }

    /**
     * @return Iterator|File[]
     */
    private function searchByUrl(string $url): Iterator
    {
        // See: https://docs.microsoft.com/en-ca/onedrive/developer/rest-api/api/shares_get#encoding-sharing-urls
        $encode = base64_encode($url);
        $sharingUrl = 'u!' . str_replace('+', '-', str_replace('/', '_', rtrim($encode, '=')));

        // Get URL info and extract driveId, fileId
        try {
            $body = $this->api->get(sprintf('/shares/%s/driveItem', $sharingUrl))->getBody();
        } catch (RequestException $e) {
            $error = Helpers::getErrorFromRequestException($e);
            switch (true) {
                // Not exists
                case $error && strpos($error, 'AccessDenied: The sharing link no longer exists') === 0:
                    throw new ShareLinkException(sprintf(
                        'The sharing link "%s..." no exists, or you do not have permission to access it.',
                        substr($url, 0, 32)
                    ), 0, $e);

                // Access denied
                case $error && strpos($error, 'AccessDenied:') === 0:
                    throw new ShareLinkException(sprintf(
                        'The sharing link "%s..." no exists, or you do not have permission to access it.',
                        substr($url, 0, 32)
                    ), 0, $e);

                // Invalid link
                case $error === 'InvalidRequest: The sharing token is invalid.':
                    throw new ShareLinkException(sprintf(
                        'The sharing link "%s..." is invalid.',
                        substr($url, 0, 32)
                    ), 0, $e);

                default:
                    throw $e;
            }
        }

        // Check mime type
        self::checkFileMimeType($body);

        // Convert to object
        yield File::from($body, []);
    }

    /**
     * @return Iterator|File[]
     */
    private function searchByText(string $search = ''): Iterator
    {
        // Normalize searched string
        $search = preg_replace('~\.xlsx$~i', '', trim($search));
        assert(is_string($search));

        // Common args
        $select = 'id,name,file,parentReference';
        $limitPerRequest = 50;
        $args = ['search' => $search, 'select' => $select, 'limit' => $limitPerRequest];

        // See: https://docs.microsoft.com/en-us/graph/api/driveitem-search
        $batch = $this->api->createBatchRequest();

        // Find files in personal OneDrive
        $uriTemplate = "/me/drive/root/search(q='{search}')?\$select={select}&\$top={limit}";
        $batch->addRequest($uriTemplate, $args, $this->getMapToFileCallback(['my'], $search));

        // Add files shared with me
        $uriTemplate = '/me/drive/sharedWithMe?$select={select}&$top={limit}';
        $batch->addRequest($uriTemplate, $args, $this->getMapToFileCallback(['shared'], $search));

        // Find files in sites
        foreach ($this->api->getSitesDrives() as $drive) {
            $driveId = urlencode($drive->getId());
            $uriTemplate = "/drives/{driveId}/search(q='{search}')?\$top={limit}";
            $batch->addRequest(
                $uriTemplate,
                array_merge($args, ['driveId' => $driveId]),
                $this->getMapToFileCallback($drive->getPath(), $search)
            );
        }

        // Fetch all in one request
        return $batch->execute();
    }

    private function getMapToFileCallback(array $path, string $search): callable
    {
        return function (array $body) use ($path, $search): Iterator {
            foreach ($body['value'] as $file) {
                $mimeType = $file['file']['mimeType'] ?? null;

                // Skip if not sheet
                if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
                    continue;
                }

                // Skip if file name doesn't contains searched string
                if ($search && strpos($file['name'], $search) === false) {
                    continue;
                }

                yield File::from($file, $path);
            }
        };
    }

    /**
     * @param mixed ...$args args for sprintf
     */
    private function log(...$args): void
    {
        $this->logger->info(sprintf(...$args));
    }

    private static function checkFileMimeType(array $body): void
    {
        $mimeType = $body['file']['mimeType'];
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidFileTypeException(sprintf(
                'File is not in the "XLSX" Excel format. Mime type: "%s"',
                $mimeType
            ));
        }
    }
}
