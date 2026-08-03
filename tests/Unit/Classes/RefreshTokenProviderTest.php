<?php

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Renatio\DropboxAdapter\Classes\RefreshTokenProvider;

function tokenResponse(string $token, int $expiresIn = 14400): Response
{
    return new Response(200, [], json_encode([
        'access_token' => $token,
        'expires_in' => $expiresIn,
        'token_type' => 'bearer',
    ]));
}

/**
 * Returns the provider plus an ArrayObject that collects the requests it sends,
 * so tests can assert on the exact HTTP traffic.
 *
 * @param  array<int, Response>  $responses
 * @return array{0: RefreshTokenProvider, 1: ArrayObject}
 */
function makeProvider(array $responses, ?Repository $cache = null): array
{
    $history = new ArrayObject;
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    $provider = new RefreshTokenProvider(
        'test-app-key',
        'test-app-secret',
        'test-refresh-token',
        new GuzzleClient(['handler' => $stack]),
        $cache ?? new Repository(new ArrayStore),
    );

    return [$provider, $history];
}

function unauthorizedException(): ClientException
{
    return new ClientException(
        'Unauthorized',
        new Request('POST', 'https://api.dropboxapi.com/2/files/list_folder'),
        new Response(401, [], '{"error_summary":"expired_access_token/"}'),
    );
}

describe('RefreshTokenProvider', function () {
    it('exchanges the refresh token for an access token', function () {
        [$provider] = makeProvider([tokenResponse('fresh-access-token')]);

        expect($provider->getToken())->toBe('fresh-access-token');
    });

    it('sends the refresh token grant with basic auth credentials', function () {
        [$provider, $history] = makeProvider([tokenResponse('fresh-access-token')]);

        $provider->getToken();

        $request = $history[0]['request'];
        parse_str((string) $request->getBody(), $body);

        expect($request->getMethod())->toBe('POST')
            ->and((string) $request->getUri())->toBe('https://api.dropbox.com/oauth2/token')
            ->and($body['grant_type'])->toBe('refresh_token')
            ->and($body['refresh_token'])->toBe('test-refresh-token')
            ->and($request->getHeaderLine('Authorization'))
            ->toBe('Basic ' . base64_encode('test-app-key:test-app-secret'));
    });

    it('caches the access token instead of refetching it', function () {
        [$provider, $history] = makeProvider([tokenResponse('cached-token')]);

        $provider->getToken();
        $provider->getToken();
        $provider->getToken();

        expect($history)->toHaveCount(1);
    });

    it('shares cached tokens between provider instances', function () {
        $cache = new Repository(new ArrayStore);

        [$first] = makeProvider([tokenResponse('shared-token')], $cache);
        $first->getToken();

        [$second, $secondHistory] = makeProvider([], $cache);

        expect($second->getToken())->toBe('shared-token')
            ->and($secondHistory)->toHaveCount(0);
    });

    it('fetches a new token when Dropbox rejects the current one', function () {
        [$provider, $history] = makeProvider([
            tokenResponse('stale-token'),
            tokenResponse('renewed-token'),
        ]);

        expect($provider->getToken())->toBe('stale-token');

        expect($provider->refresh(unauthorizedException()))->toBeTrue();

        expect($provider->getToken())->toBe('renewed-token')
            ->and($history)->toHaveCount(2);
    });

    it('ignores errors that are not authorization failures', function () {
        [$provider, $history] = makeProvider([tokenResponse('unchanged-token')]);
        $provider->getToken();

        $conflict = new ClientException(
            'Conflict',
            new Request('POST', 'https://api.dropboxapi.com/2/files/upload'),
            new Response(409, [], '{"error_summary":"path/conflict/"}'),
        );

        expect($provider->refresh($conflict))->toBeFalse()
            ->and($provider->getToken())->toBe('unchanged-token')
            ->and($history)->toHaveCount(1);
    });

    it('expires the cached token before Dropbox does', function () {
        $cache = Mockery::mock(Repository::class);
        $cache->shouldReceive('get')->andReturnNull();
        $cache->shouldReceive('put')
            ->once()
            ->with(Mockery::any(), 'ttl-token', 13800)
            ->andReturnTrue();

        [$provider] = makeProvider([tokenResponse('ttl-token', 14400)], $cache);

        expect($provider->getToken())->toBe('ttl-token');
    });

    it('fails when Dropbox returns no access token', function () {
        [$provider] = makeProvider([new Response(200, [], json_encode(['token_type' => 'bearer']))]);

        expect(fn () => $provider->getToken())->toThrow(RuntimeException::class);
    });
});
