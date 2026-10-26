<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Api;

use GuzzleHttp\Exception\ServerException;
use Keboola\OneDriveExtractor\Exception\AccessDeniedException;
use Keboola\OneDriveExtractor\Exception\BatchRequestException;
use Throwable;
use Iterator;
use GuzzleHttp\Exception\RequestException;
use Keboola\OneDriveExtractor\Api\Model\File;
use Keboola\OneDriveExtractor\Exception\InvalidFileTypeException;
use Keboola\OneDriveExtractor\Exception\ResourceNotFoundException;
use Keboola\OneDriveExtractor\Exception\ShareLinkException;
use Psr\Log\LoggerInterface;

class WorkbooksFinder
{
    // The Search API is an extra source, so a failing request must not slow the job down
    public const SEARCH_API_MAX_ATTEMPTS = 3;

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
        } catch (RequestException|AccessDeniedException $e) {
            $error = Helpers::getErrorFromRequestException($e) ?? $e->getMessage();
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
        $batch->addRequest(
            $uriTemplate,
            $args,
            $this->getMapToFileCallback(['my'], $search),
            $this->getExceptionProcessor('me drive', $search)
        );

        // Add files shared with me.
        // DEPRECATED: this endpoint operates in a degraded state and stops returning data in November 2026.
        // See: https://learn.microsoft.com/en-us/graph/api/drive-sharedwithme
        // It is kept, because it is the only source of shared files for personal Microsoft accounts,
        // which the Graph Search API (see searchSharedFilesByGraphSearch) does not support.
        $uriTemplate = '/me/drive/sharedWithMe?$select={select}&$top={limit}';
        $batch->addRequest(
            $uriTemplate,
            $args,
            $this->getMapToFileCallback(['shared'], $search),
            $this->getExceptionProcessor('shared files', $search)
        );

        // Find files in sites
        try {
            foreach ($this->api->getSitesDrives() as $drive) {
                $uriTemplate = "/drives/{driveId}/search(q='{search}')?\$top={limit}";
                $batch->addRequest(
                    $uriTemplate,
                    array_merge($args, ['driveId' => $drive->getId()]),
                    $this->getMapToFileCallback($drive->getPath(), $search),
                    $this->getExceptionProcessor(
                        sprintf('SharePoint site "%s"', $drive->getSite()->getName()),
                        $search
                    )
                );
            }
        } catch (ServerException $exception) {
            // 'Error when searching for sites: ' . $exception->getMessage());
        }

        // Fetch all in one request.
        // If no file is found, shared files are searched again with the Graph Search API.
        return $this->searchSharedFilesIfNothingFound($batch->execute(), $search, $limitPerRequest);
    }

    /**
     * Yields the files from the other sources. Only if they find nothing, the Graph Search API is used.
     *
     * The fallback cannot change the result of a configuration that works now:
     * - If the other sources find one or more files, the Search API is not called.
     * - Therefore the Search API can only change "no file found" to "one or more files found".
     *
     * @param iterable<File> $files files from the other sources
     * @return Iterator|File[]
     */
    private function searchSharedFilesIfNothingFound(iterable $files, string $search, int $limit): Iterator
    {
        $found = false;
        foreach ($files as $file) {
            $found = true;
            yield $file;
        }

        if ($found) {
            return;
        }

        foreach ($this->searchSharedFilesByGraphSearch($search, $limit) as $file) {
            yield $file;
        }
    }

    /**
     * Searches for files with the Microsoft Graph Search API.
     *
     * This is the replacement source for the deprecated "/me/drive/sharedWithMe" endpoint,
     * which stops returning data in November 2026.
     * The Search API reads the SharePoint index, so it also finds files that are shared
     * with the user, but are not in the personal drive or in an enumerated SharePoint site.
     * See: https://learn.microsoft.com/en-us/graph/api/search-query
     *
     * The deprecated endpoint is kept as a second source, because the Search API
     * is not supported for personal Microsoft accounts.
     *
     * An error is only caught, never thrown, so a failed search behaves as before.
     *
     * @return Iterator|File[]
     */
    private function searchSharedFilesByGraphSearch(string $search, int $limit): Iterator
    {
        // KQL: search the file name, and take only XLSX files, because only they can be read
        $query = $search === ''
            ? 'filetype:xlsx'
            : sprintf('%s AND filetype:xlsx', Helpers::toKqlPhrase($search));

        try {
            $response = $this->api->post(
                '/search/query',
                [],
                [
                    'requests' => [
                        [
                            'entityTypes' => ['driveItem'],
                            'query' => ['queryString' => $query],
                            'from' => 0,
                            'size' => $limit,
                        ],
                    ],
                ],
                [],
                self::SEARCH_API_MAX_ATTEMPTS
            );
            $items = Helpers::extractDriveItemsFromSearchResponse($response->getBody());

            $files = [];
            foreach ($items as $item) {
                $file = self::mapSearchItemToFile($item, $search);
                if ($file !== null) {
                    $files[] = $file;
                }
            }
        } catch (Throwable $e) {
            // The Search API is one source of many, a problem must not stop the search
            $this->logger->warning(sprintf(
                'Error when searching for "%s" with the Search API: "%s".',
                $search,
                Helpers::getErrorFromRequestException($e) ?? $e->getMessage(),
            ));
            return;
        }

        foreach ($files as $file) {
            yield $file;
        }
    }

    /**
     * Converts one "driveItem" from the Search API to a File, or returns null if it must be skipped.
     *
     * The Search API does not return the "file" facet, so the XLSX type is checked with the file name.
     * The name filter is the same as in getMapToFileCallback, so both sources behave the same.
     *
     * The method is public, because it is a pure function and it is tested directly.
     */
    public static function mapSearchItemToFile(array $item, string $search): ?File
    {
        $fileId = $item['id'] ?? null;
        $name = $item['name'] ?? null;
        $parentReference = $item['parentReference'] ?? null;
        $driveId = is_array($parentReference) ? ($parentReference['driveId'] ?? null) : null;

        // Skip if a needed value is missing or empty
        if (!is_string($fileId) || !is_string($name) || !is_string($driveId)) {
            return null;
        }
        if ($fileId === '' || $name === '' || $driveId === '') {
            return null;
        }

        // Skip if not an XLSX file, only XLSX files can be accessed through the API
        if (preg_match('~\.xlsx$~i', $name) !== 1) {
            return null;
        }

        // Skip if file name doesn't contains searched string
        if ($search && strpos($name, $search) === false) {
            return null;
        }

        return File::from($item, ['shared']);
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

                // Skip if missing driveId
                if (!isset($file['parentReference']['driveId'])) {
                    continue;
                }

                yield File::from($file, $path);
            }
        };
    }

    private function getExceptionProcessor(string $target, string $search): callable
    {
        return function (Throwable $e) use ($target, $search): void {
            $e = Helpers::processRequestException($e);
            if ($e instanceof BatchRequestException) {
                $this->logger->warning(sprintf(
                    'Error when searching for "%s" in %s: "%s" (%d).',
                    $search,
                    $target,
                    $e->getOriginalMessage(),
                    $e->getCode(),
                ));
                return;
            }

            throw $e;
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
        if (!isset($body['file'])) {
            throw new InvalidFileTypeException('File type cannot be recognized.');
        }
        $mimeType = $body['file']['mimeType'];
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidFileTypeException(sprintf(
                'File is not in the "XLSX" Excel format. Mime type: "%s"',
                $mimeType
            ));
        }
    }
}
