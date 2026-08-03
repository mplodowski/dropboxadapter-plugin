<?php

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Renatio\DropboxAdapter\Classes\RetryHandler;

/**
 * Builds a client whose retry delays are captured instead of slept through, so tests
 * can assert on the backoff without actually waiting.
 *
 * @param  array<int, Response|Throwable>  $responses
 * @return array{0: GuzzleClient, 1: ArrayObject}
 */
function retryingClient(array $responses): array
{
    $delays = new ArrayObject;
    $stack = RetryHandler::stack(new MockHandler($responses));

    $stack->push(function (callable $handler) use ($delays) {
        return function ($request, array $options) use ($handler, $delays) {
            if (isset($options['delay'])) {
                $delays[] = $options['delay'];
                $options['delay'] = 0;
            }

            return $handler($request, $options);
        };
    });

    return [new GuzzleClient(['handler' => $stack]), $delays];
}

function throttled(?string $retryAfter = null): Response
{
    return new Response(429, $retryAfter === null ? [] : ['Retry-After' => $retryAfter]);
}

describe('RetryHandler', function () {
    it('retries a throttled request until it succeeds', function () {
        [$client] = retryingClient([throttled(), throttled(), new Response(200, [], 'ok')]);

        $response = $client->request('GET', 'https://api.dropboxapi.com/test');

        expect($response->getStatusCode())->toBe(200);
    });

    it('retries server errors', function () {
        [$client] = retryingClient([new Response(503), new Response(200, [], 'ok')]);

        expect($client->request('GET', 'https://api.dropboxapi.com/test')->getStatusCode())->toBe(200);
    });

    it('retries connection failures', function () {
        [$client] = retryingClient([
            new ConnectException('Connection refused', new Request('GET', 'https://api.dropboxapi.com/test')),
            new Response(200, [], 'ok'),
        ]);

        expect($client->request('GET', 'https://api.dropboxapi.com/test')->getStatusCode())->toBe(200);
    });

    it('does not retry client errors that are not throttling', function () {
        [$client, $delays] = retryingClient([new Response(409, [], 'conflict'), new Response(200)]);

        $response = $client->request('GET', 'https://api.dropboxapi.com/test', ['http_errors' => false]);

        expect($response->getStatusCode())->toBe(409)
            ->and($delays)->toHaveCount(0);
    });

    it('gives up after the retry limit', function () {
        [$client] = retryingClient([throttled(), throttled(), throttled(), throttled()]);

        expect(fn () => $client->request('GET', 'https://api.dropboxapi.com/test'))
            ->toThrow(GuzzleHttp\Exception\ClientException::class);
    });

    it('backs off exponentially when no Retry-After is given', function () {
        [$client, $delays] = retryingClient([throttled(), throttled(), new Response(200)]);

        $client->request('GET', 'https://api.dropboxapi.com/test');

        expect($delays->getArrayCopy())->toBe([1000, 2000]);
    });

    it('honours a Retry-After header given in seconds', function () {
        [$client, $delays] = retryingClient([throttled('7'), new Response(200)]);

        $client->request('GET', 'https://api.dropboxapi.com/test');

        expect($delays->getArrayCopy())->toBe([7000]);
    });

    it('honours a Retry-After header given as a date', function () {
        [$client, $delays] = retryingClient([
            throttled(gmdate('D, d M Y H:i:s \G\M\T', time() + 5)),
            new Response(200),
        ]);

        $client->request('GET', 'https://api.dropboxapi.com/test');

        expect($delays[0])->toBeGreaterThan(3000)->toBeLessThanOrEqual(5000);
    });

    it('caps an unreasonably long Retry-After', function () {
        [$client, $delays] = retryingClient([throttled('3600'), new Response(200)]);

        $client->request('GET', 'https://api.dropboxapi.com/test');

        expect($delays->getArrayCopy())->toBe([60000]);
    });

    it('ignores a malformed Retry-After and falls back to backoff', function () {
        [$client, $delays] = retryingClient([throttled('not-a-date'), new Response(200)]);

        $client->request('GET', 'https://api.dropboxapi.com/test');

        expect($delays->getArrayCopy())->toBe([1000]);
    });

    it('ignores a Retry-After pointing into the past', function () {
        [$client, $delays] = retryingClient([
            throttled(gmdate('D, d M Y H:i:s \G\M\T', time() - 120)),
            new Response(200),
        ]);

        $client->request('GET', 'https://api.dropboxapi.com/test');

        expect($delays->getArrayCopy())->toBe([1000]);
    });
});
