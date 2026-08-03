<?php

namespace Renatio\DropboxAdapter\Classes;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Spatie\Dropbox\RefreshableTokenProvider;

/**
 * Exchanges a long-lived refresh token for short-lived Dropbox access tokens.
 *
 * Dropbox is phasing out long-lived access tokens, so apps must obtain an access
 * token from a refresh token and renew it once Dropbox rejects it. Tokens are cached
 * so a token is not re-fetched on every request across the application.
 */
class RefreshTokenProvider implements RefreshableTokenProvider
{
    protected const TOKEN_ENDPOINT = 'https://api.dropbox.com/oauth2/token';

    /**
     * Dropbox access tokens live for 4 hours; expire early to avoid racing the deadline.
     */
    protected const TOKEN_TTL = 3 * 3600;

    protected ClientInterface $httpClient;

    public function __construct(
        protected string $appKey,
        protected string $appSecret,
        protected string $refreshToken,
        ?ClientInterface $httpClient = null,
        protected ?CacheRepository $cache = null,
    ) {
        $this->httpClient = $httpClient ?? new GuzzleClient;
    }

    public function getToken(): string
    {
        $cached = $this->cache()->get($this->cacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->fetchToken();
    }

    public function refresh(ClientException $exception): bool
    {
        if ($exception->getResponse()?->getStatusCode() !== 401) {
            return false;
        }

        $this->cache()->forget($this->cacheKey());

        $this->fetchToken();

        return true;
    }

    protected function fetchToken(): string
    {
        $response = $this->httpClient->request('POST', self::TOKEN_ENDPOINT, [
            'auth' => [$this->appKey, $this->appSecret],
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refreshToken,
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);

        $token = $payload['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Dropbox did not return an access token for the given refresh token.');
        }

        $this->cache()->put($this->cacheKey(), $token, $this->tokenLifetime($payload));

        return $token;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function tokenLifetime(array $payload): int
    {
        $expiresIn = $payload['expires_in'] ?? null;

        if (! is_numeric($expiresIn)) {
            return self::TOKEN_TTL;
        }

        return max(60, (int) $expiresIn - 600);
    }

    protected function cacheKey(): string
    {
        return 'renatio.dropboxadapter.token.' . sha1($this->appKey . $this->refreshToken);
    }

    protected function cache(): CacheRepository
    {
        return $this->cache ??= Cache::store();
    }
}
