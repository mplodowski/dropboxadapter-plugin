<?php

namespace Renatio\DropboxAdapter\Providers;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use League\Flysystem\Filesystem;
use Renatio\DropboxAdapter\Classes\RefreshTokenProvider;
use Renatio\DropboxAdapter\Classes\RetryHandler;
use Spatie\Dropbox\Client;
use Spatie\Dropbox\TokenProvider;
use Spatie\FlysystemDropbox\DropboxAdapter;

class DropboxServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Storage::extend('dropbox', function ($app, $config) {
            $adapter = new DropboxAdapter(new Client(
                $this->resolveTokenProvider($config),
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
     * Resolves credentials into a token provider, preferring OAuth2 refresh tokens
     * over the legacy long-lived access token that Dropbox is phasing out.
     *
     * @param  array<string, mixed>  $config
     */
    protected function resolveTokenProvider(array $config): TokenProvider|string
    {
        $refreshCredentials = [
            'app_key' => $this->stringOrNull($config, 'app_key'),
            'app_secret' => $this->stringOrNull($config, 'app_secret'),
            'refresh_token' => $this->stringOrNull($config, 'refresh_token'),
        ];

        if (array_filter($refreshCredentials) !== []) {
            return $this->makeRefreshTokenProvider($refreshCredentials);
        }

        $authorizationToken = $this->stringOrNull($config, 'authorization_token');

        if ($authorizationToken === null) {
            throw new InvalidArgumentException(
                'Dropbox disk is missing credentials. Provide either an [authorization_token], ' .
                'or [app_key], [app_secret] and [refresh_token] for OAuth2 refresh tokens.',
            );
        }

        return $authorizationToken;
    }

    /**
     * @param  array{app_key: ?string, app_secret: ?string, refresh_token: ?string}  $credentials
     */
    protected function makeRefreshTokenProvider(array $credentials): RefreshTokenProvider
    {
        $missing = array_keys($credentials, null, true);

        if ($missing !== []) {
            throw new InvalidArgumentException(
                'Dropbox disk is missing [' . implode('], [', $missing) . '] required for OAuth2 refresh tokens.',
            );
        }

        return new RefreshTokenProvider(
            $credentials['app_key'],
            $credentials['app_secret'],
            $credentials['refresh_token'],
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function stringOrNull(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
