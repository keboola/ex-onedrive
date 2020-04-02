<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\FunctionalTests\Fixtures;

use Iterator;
use Throwable;
use RuntimeException;
use GuzzleHttp\Exception\ClientException;
use Keboola\OneDriveExtractor\Api\GraphApiFactory;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphResponse;
use Microsoft\Graph\Model;
use Symfony\Component\Finder\Finder;

class FixturesUtils
{
    private Graph $graphApi;

    public function __construct()
    {
        $this->graphApi = $this->createGraphApi();
    }

    public function getMeDriveId(): string
    {
        $response = $this->graphApi->createRequest('get', '/me/drive?$select=id')->execute();
        assert($response instanceof GraphResponse);
        $body = $response->getBody();
        return $body['id'];
    }

    public function getSharePointSiteDriveId(string $siteName): string
    {
        // Load site
        $siteName = urlencode($siteName);
        $response = $this
            ->graphApi
            ->createRequest('get', "/sites?search=$siteName&\$select=id,name")
            ->execute();
        assert($response instanceof GraphResponse);
        $body = $response->getBody();
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
        $response = $this
            ->graphApi
            ->createRequest('get', "/sites/{$siteId}/drive?\$select=id")
            ->execute();
        assert($response instanceof GraphResponse);
        $body = $response->getBody();
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
                            $url = $this->pathToUrl($driveId, $relativePath . '/' . $name);
                            $this->graphApi->createRequest('DELETE', $url)->execute();
                        } catch (ClientException $e) {
                            // ignore if file not exits
                        }
                    }

                    if ($retry-- <= 0) {
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
        $uploadFragSize = 320 * 1024 * 10; // 3.2 MiB
        $fileSize = filesize($localPath);
        $path = $relativePath . '/' . $name;
        $url = $this->pathToUrl($driveId, $relativePath . '/' . $name);

        // Create upload session
        /** @var Model\UploadSession $uploadSession */
        $uploadSession = $this->graphApi
            ->createRequest('POST', $url . 'createUploadSession')
            ->attachBody(['@microsoft.graph.conflictBehavior'=> 'replace' ])
            ->setReturnType(Model\UploadSession::class)
            ->setTimeout('1000')
            ->execute();

        // Upload file in parts
        $file = fopen($localPath, 'r');
        if (!$file) {
            throw new RuntimeException(sprintf('Cannot open file "%s".', $localPath));
        }

        try {
            while (!feof($file)) {
                $start = ftell($file);
                $data = fread($file, $uploadFragSize);
                $end = ftell($file);
                $uploadSession = $this->graphApi
                    ->createRequest('PUT', $uploadSession->getUploadUrl())
                    ->addHeaders([
                        'Content-Length' => $end - $start,
                        'Content-Range' => sprintf('bytes %d-%d/%d', $start, $end-1, $fileSize),
                    ])
                    ->attachBody($data)
                    ->setReturnType(Model\UploadSession::class)
                    ->setTimeout('1000')
                    ->execute();
            }
        } finally {
            fclose($file);
        }

        // Uploaded
        $fileId = $uploadSession->getId();
        FixturesUtils::log(sprintf('"%s" - uploaded', $path));

        // Create sharing link (for search by url tests)
        /** @var GraphResponse $linkResponse */
        $linkResponse = $this->graphApi
            ->createRequest('POST', $url . 'createLink')
            ->attachBody([
                'type' => 'view',
                'scope' => 'organization',
            ])
            ->execute();
        $linkBody = $linkResponse->getBody();
        $sharingLink = $linkBody['link']['webUrl'];
        FixturesUtils::log(sprintf('"%s" - created sharing link', $path));

        // Load worksheets if XLSX file
        $worksheets = iterator_to_array($this->loadWorksheets($path, $driveId, $fileId));
        yield $path => new File($path, $driveId, $fileId, $sharingLink, $worksheets);
    }

    private function loadWorksheets(string $path, string $driveId, string $fileId): Iterator
    {
        if (preg_match('~\.xlsx$~', $path)) {
            $worksheetsResponse = $this->graphApi
                ->createRequest(
                    'get',
                    "/drives/{$driveId}/items/{$fileId}/workbook/worksheets?\$select=id,position"
                )
                ->execute();
            assert($worksheetsResponse instanceof GraphResponse);
            foreach ($worksheetsResponse->getBody()['value'] as $item) {
                yield $item['position'] => $item['id'];
            }
            FixturesUtils::log(sprintf('"%s" - loaded worksheet ids', $path));
        }
    }

    private function pathToUrl(string $driveId, string $path): string
    {
        $driveId = urlencode($driveId);
        $path = trim($path, '/');
        $path = $path ? (':/' . trim($path, '/') . ':/') : '/';
        return "/drives/{$driveId}/root{$path}";
    }

    private function createGraphApi(): Graph
    {
        $apiFactory = new GraphApiFactory();
        return $apiFactory->create(
            (string) getenv('OAUTH_APP_ID'),
            (string) getenv('OAUTH_APP_SECRET'),
            [
                'access_token' => getenv('OAUTH_ACCESS_TOKEN'),
                'refresh_token' => getenv('OAUTH_REFRESH_TOKEN'),
            ]
        );
    }

    public function log(string $text): void
    {

        echo empty($text) ? "\n" : "FixturesUtils: {$text}\n";
    }
}
