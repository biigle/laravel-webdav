<?php

namespace Biigle\WebDav;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Sabre\DAV\Client as WebDAVClient;
use GuzzleHttp\Client as GuzzleClient;

class WebDavServiceProvider extends ServiceProvider
{
    const DISK_TYPE = 'webdav';

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        Storage::extend(static::DISK_TYPE, function ($app, $config) {
            $pathPrefix = $config['pathPrefix'] ?? null;

            $guzzleConfig = [];
            if (array_key_exists('proxy', $config)) {
                $guzzleConfig['proxy'] = $config['proxy'];
            }

            $tokenAuth = false;

            if (!empty($config['userName'] ?? false) || !empty($config['password'] ?? false)) {
                $guzzleConfig['auth'] = [$config['userName'], $config['password']];

                if (!array_key_exists('authType', $config)) {
                    $config['authType'] = 1;
                } else if ($config['authType'] === 2) {
                    array_push($guzzleConfig['auth'], 'digest');
                }  else if ($config['authType'] === 4) {
                    array_push($guzzleConfig['auth'], 'ntlm');
                }
            } else if (!empty($config['token'] ?? false)) {
                $tokenAuth = true;
            }

            $webdavClient = new WebDAVClient($config);

            if ($tokenAuth) {
                $token = 'Bearer ' . $config['token'];
                $guzzleConfig['headers'] = ['Authorization' => $token];
                $webdavClient->on('beforeRequest', fn ($request) => $request->setHeader('Authorization', $token));
            }

            $guzzleClient = new GuzzleClient($guzzleConfig);

            $adapter = $this->getWebDavAdapter($webdavClient, $guzzleClient, $pathPrefix);

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }

    /**
     * Build and return the WebDAV adapter. This simplifies extension by other packages.
     */
    protected function getWebDavAdapter($webdavClient, $guzzleClient, $pathPrefix): WebDAVAdapter
    {
        return new WebDAVAdapter($webdavClient, $guzzleClient, $pathPrefix);
    }
}
