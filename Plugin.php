<?php

namespace Renatio\DropboxAdapter;

use Renatio\DropboxAdapter\Providers\DropboxServiceProvider;
use System\Classes\PluginBase;

/**
 * Class Plugin
 * @package Renatio\DropboxAdapter
 */
class Plugin extends PluginBase
{

    /**
     * @return array
     */
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

    /**
     * @return void
     */
    public function boot()
    {
        $this->app->register(DropboxServiceProvider::class);
    }

}