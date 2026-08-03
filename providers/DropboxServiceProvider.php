<?php

namespace Renatio\DropboxAdapter\Providers;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Renatio\DropboxAdapter\Classes\RetryHandler;
use Renatio\DropboxAdapter\Classes\TokenProviderFactory;
use Renatio\DropboxAdapter\Models\Settings;
use Spatie\Dropbox\Client;
use Spatie\FlysystemDropbox\DropboxAdapter;
use Throwable;

class DropboxServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Storage::extend('dropbox', function ($app, $config) {
            $config = $this->withSettingsFallback($config);

            $adapter = new DropboxAdapter(new Client(
                (new TokenProviderFactory)->make($config),
                new GuzzleClient(['handler' => RetryHandler::stack()]),
            ));

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config,
            );
        });
    }

    /**
     * Fills credentials the disk configuration omits with those saved in the backend.
     * Values in config/filesystems.php always win, so existing installations keep
     * behaving exactly as before.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function withSettingsFallback(array $config): array
    {
        if ($this->hasCredentials($config)) {
            return $config;
        }

        return array_merge($config, $this->settingsCredentials());
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function hasCredentials(array $config): bool
    {
        foreach (['authorization_token', 'app_key', 'app_secret', 'refresh_token'] as $key) {
            if (isset($config[$key]) && is_string($config[$key]) && trim($config[$key]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Settings live in the database, which is unavailable during install and in
     * console contexts that run before migrations.
     *
     * @return array<string, string>
     */
    protected function settingsCredentials(): array
    {
        try {
            return Settings::credentials();
        } catch (Throwable) {
            return [];
        }
    }
}
