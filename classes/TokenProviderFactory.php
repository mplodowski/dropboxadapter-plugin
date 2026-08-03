<?php

namespace Renatio\DropboxAdapter\Classes;

use InvalidArgumentException;
use Spatie\Dropbox\TokenProvider;

/**
 * Turns disk configuration into Dropbox credentials, preferring OAuth2 refresh tokens
 * over the legacy long-lived access token that Dropbox is phasing out.
 */
class TokenProviderFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function make(array $config): TokenProvider|string
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
                trans('renatio.dropboxadapter::lang.exception.missing_credentials'),
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
                trans('renatio.dropboxadapter::lang.exception.incomplete_credentials', [
                    'keys' => implode(', ', $missing),
                ]),
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
