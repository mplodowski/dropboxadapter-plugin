<?php

namespace Renatio\DropboxAdapter\Providers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Spatie\Dropbox\Client as DropboxClient;
use Spatie\FlysystemDropbox\DropboxAdapter;

class DropboxServiceProvider extends ServiceProvider
{

    public function boot()
    {
        Storage::extend('dropbox', function ($app, $config) {
            $client = new DropboxClient(
                $config['authorizationToken']
            );

            $adapter = new DropboxAdapter($client);

            return new Filesystem($adapter, ['case_sensitive' => false]);
        });
    }

    public function register()
    {
        //
    }
}
