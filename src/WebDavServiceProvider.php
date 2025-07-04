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

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        Storage::extend('webdav', function ($app, $config) {
            $pathPrefix = $config['pathPrefix'] ?? null;

            $guzzleConfig = [];
            if (array_key_exists('proxy', $config)) {
                $guzzleConfig['proxy'] = $config['proxy'];
            }

            if (!empty($config['userName'] ?? false) || !empty($config['password'] ?? false)) {
                $guzzleConfig['auth'] = [$config['userName'], $config['password']];

                if (!array_key_exists('authType', $config)) {
                    $config['authType'] = 1;
                } else if ($config['authType'] === 2) {
                    array_push($guzzleConfig['auth'], 'digest');
                }  else if ($config['authType'] === 4) {
                    array_push($guzzleConfig['auth'], 'ntlm');
                }
            }

            $webdavClient = new WebDAVClient($config);
            $guzzleClient = new GuzzleClient($guzzleConfig);

            $adapter = new WebDAVAdapter($webdavClient, $guzzleClient, $pathPrefix);

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }
}
