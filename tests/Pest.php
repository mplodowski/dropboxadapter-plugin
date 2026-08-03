<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration for DropboxAdapter Plugin Tests
|--------------------------------------------------------------------------
|
| This configuration file ensures that DropboxAdapter tests use the correct
| test case class and have access to all helper functions.
|
*/

// Include the custom TestCase for this plugin
require_once __DIR__ . '/TestCase.php';

// Use TestCase for all tests in this directory with October CMS setup
uses(Renatio\DropboxAdapter\Tests\TestCase::class)
    ->beforeEach(function () {
        $this->setUpOctoberPlugin();
    })
    ->afterEach(function () {
        $this->tearDownOctoberPlugin();
    })
    ->in(__DIR__);

// Custom expectations
expect()->extend('toBeStorageDisk', function () {
    $disk = $this->value;

    return expect($disk)->toBeInstanceOf(\Illuminate\Contracts\Filesystem\Filesystem::class);
});

// Helper functions
function createDropboxConfig(array $overrides = []): array
{
    return array_merge([
        'driver' => 'dropbox',
        'authorization_token' => 'test-token-' . uniqid(),
    ], $overrides);
}

function mockDropboxClient(): object
{
    return new class
    {
        public function __call($method, $args)
        {
            return $this;
        }
    };
}
