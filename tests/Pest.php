<?php

use Illuminate\Filesystem\FilesystemAdapter;
use Spatie\Dropbox\Client;
use Spatie\Dropbox\TokenProvider;
use Spatie\FlysystemDropbox\DropboxAdapter;

require_once __DIR__ . '/TestCase.php';

uses(Renatio\DropboxAdapter\Tests\TestCase::class)
    ->beforeEach(function () {
        $this->setUpOctoberPlugin();
        $this->forgetSettings();
    })
    ->afterEach(function () {
        $this->tearDownOctoberPlugin();
    })
    ->in(__DIR__);

/**
 * Asserts the value is a Laravel disk actually backed by the Dropbox adapter,
 * not merely any Filesystem implementation.
 */
expect()->extend('toBeDropboxDisk', function () {
    expect($this->value)->toBeInstanceOf(FilesystemAdapter::class)
        ->and($this->value->getAdapter())->toBeInstanceOf(DropboxAdapter::class);

    return $this;
});

/**
 * Extracts the token provider from the Dropbox client behind a disk, so tests can
 * assert which credentials the provider actually handed to the SDK.
 */
function dropboxTokenProvider(FilesystemAdapter $disk): TokenProvider
{
    return (new ReflectionProperty(Client::class, 'tokenProvider'))->getValue(dropboxClient($disk));
}

/**
 * Renders the Guzzle handler stack behind a disk, so tests can assert which
 * middleware the Dropbox client was given.
 */
function dropboxHandlerStack(FilesystemAdapter $disk): string
{
    $httpClient = (new ReflectionProperty(Client::class, 'client'))->getValue(dropboxClient($disk));

    return (string) $httpClient->getConfig('handler');
}

function dropboxClient(FilesystemAdapter $disk): Client
{
    return (new ReflectionProperty(DropboxAdapter::class, 'client'))->getValue($disk->getAdapter());
}

/**
 * Replaces the HTTP client behind a disk with mocked responses, so Dropbox API calls
 * can be exercised without network access.
 *
 * @param  array<int, GuzzleHttp\Psr7\Response|Throwable>  $responses
 */
function mockDropboxHttp(FilesystemAdapter $disk, array $responses): ArrayObject
{
    $history = new ArrayObject;
    $stack = GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler($responses));
    $stack->push(GuzzleHttp\Middleware::history($history));

    $property = new ReflectionProperty(Client::class, 'client');
    $property->setValue(dropboxClient($disk), new GuzzleHttp\Client(['handler' => $stack]));

    return $history;
}
