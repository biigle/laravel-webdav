<?php

namespace Biigle\WebDav;

use GuzzleHttp\Client as GuzzleClient;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\WebDAV\WebDAVAdapter as Base;
use Sabre\DAV\Client as WebADVClient;
use Sabre\HTTP\ClientHttpException;

class WebDAVAdapter extends Base
{
    protected PathPrefixer $prefixer;

    public function __construct(
        protected WebADVClient $client,
        protected GuzzleClient $guzzle,
        string $prefix = '',
        protected string $visibilityHandling = self::ON_VISIBILITY_THROW_ERROR,
        protected bool $manualCopy = false,
        protected bool $manualMove = false,
    ) {
        parent::__construct($client, $prefix, $visibilityHandling, $manualCopy, $manualMove);
        $this->prefixer = new PathPrefixer($prefix);
    }

    public function readStream(string $path)
    {
        $location = $this->encodePath($this->prefixer->prefixPath($path));

        try {
            $url = $this->client->getAbsoluteUrl($location);
            $response = $this->guzzle->request('GET', $url, ['stream' => true]);
            return $response->getBody()->detach();
        } catch (Throwable $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            foreach (parent::listContents($path, $deep) as $item) {
                yield $item;
            }
        } catch (ClientHttpException $e) {
            if ($e->getHttpStatus() === 404) {
                return;
            }
            throw $e;
        }
    }
}
