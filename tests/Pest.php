<?php

use Illuminate\Filesystem\FilesystemAdapter;
use Spatie\Dropbox\Client;
use Spatie\Dropbox\TokenProvider;
use Spatie\FlysystemDropbox\DropboxAdapter;

require_once __DIR__ . '/TestCase.php';

uses(Renatio\DropboxAdapter\Tests\TestCase::class)
    ->beforeEach(function () {
        $this->setUpOctoberPlugin();
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
