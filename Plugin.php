<?php

namespace Renatio\DropboxAdapter;

use Renatio\DropboxAdapter\Providers\DropboxServiceProvider;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{

    public function pluginDetails()
    {
        return [
            'name' => 'renatio.dropboxadapter::lang.plugin.name',
            'description' => 'renatio.dropboxadapter::lang.plugin.description',
            'author' => 'Renatio',
            'icon' => 'icon-dropbox',
            'homepage' => 'http://octobercms.com/plugin/renatio-dropboxadapter',
        ];
    }

    public function boot()
    {
        $this->app->register(DropboxServiceProvider::class);
    }
}
