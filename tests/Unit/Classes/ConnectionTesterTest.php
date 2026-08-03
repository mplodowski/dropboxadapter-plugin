<?php

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Renatio\DropboxAdapter\Classes\ConnectionTester;

/**
 * Swaps in a mocked Dropbox client so the tester can be exercised without network access.
 *
 * @param  array<int, Response|Throwable>  $responses
 */
function testerWithResponses(array $responses): ConnectionTester
{
    $httpClient = new GuzzleClient(['handler' => HandlerStack::create(new MockHandler($responses))]);

    return new class($httpClient) extends ConnectionTester
    {
        public function __construct(protected GuzzleClient $httpClient)
        {
        }

        protected function client(array $config): Spatie\Dropbox\Client
        {
            return new Spatie\Dropbox\Client(
                (new Renatio\DropboxAdapter\Classes\TokenProviderFactory)->make($config),
                $this->httpClient,
            );
        }
    };
}

function accountResponse(string $displayName = 'Jane Doe'): Response
{
    return new Response(200, [], json_encode([
        'account_id' => 'dbid:AAA',
        'name' => ['display_name' => $displayName],
        'email' => 'jane@example.com',
    ]));
}

describe('ConnectionTester', function () {
    it('reports success and the account name', function () {
        $result = testerWithResponses([accountResponse('Jane Doe')])
            ->test(['authorization_token' => 'valid-token']);

        expect($result['success'])->toBeTrue()
            ->and($result['message'])->toContain('Jane Doe');
    });

    it('falls back to the email when no display name is present', function () {
        $response = new Response(200, [], json_encode(['email' => 'jane@example.com']));

        $result = testerWithResponses([$response])->test(['authorization_token' => 'valid-token']);

        expect($result['success'])->toBeTrue()
            ->and($result['message'])->toContain('jane@example.com');
    });

    it('reports failure when Dropbox rejects the credentials', function () {
        $result = testerWithResponses([new Response(401, [], '{"error_summary":"invalid_access_token/"}')])
            ->test(['authorization_token' => 'bad-token']);

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->not->toBeEmpty();
    });

    it('reports failure when credentials are missing entirely', function () {
        $result = testerWithResponses([])->test([]);

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('authorization_token');
    });

    it('reports failure when refresh credentials are incomplete', function () {
        $result = testerWithResponses([])->test(['app_key' => 'key']);

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('app_secret');
    });

    it('reports failure for an unconfigured disk', function () {
        $result = (new ConnectionTester)->testDisk('no_such_disk');

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('no_such_disk');
    });

    it('never throws, so the settings page can always render a result', function () {
        $result = testerWithResponses([new Response(500, [], 'server exploded')])
            ->test(['authorization_token' => 'valid-token']);

        expect($result)->toHaveKeys(['success', 'message'])
            ->and($result['success'])->toBeFalse();
    });
});
