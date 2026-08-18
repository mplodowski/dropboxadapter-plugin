<?php

namespace Renatio\DropboxAdapter\Providers;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Renatio\DropboxAdapter\Classes\RetryHandler;
use Renatio\DropboxAdapter\Classes\TemporaryUrlAdapter;
use Renatio\DropboxAdapter\Classes\TokenProviderFactory;
use Renatio\DropboxAdapter\Models\Settings;
use Spatie\Dropbox\Client;
use Throwable;

class DropboxServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /** Laravel 13 rebinds the callback to the FilesystemManager, so $this is not the provider here. */
        $provider = $this;

        Storage::extend('dropbox', function ($app, $config) use ($provider) {
            $config = $provider->withSettingsFallback($config);

            $client = new Client(
                (new TokenProviderFactory)->make($config),
                new GuzzleClient(['handler' => RetryHandler::stack()]),
            );

            $adapter = new TemporaryUrlAdapter($client, rtrim($config['root'] ?? '', '/'));

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
    public function withSettingsFallback(array $config): array
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
