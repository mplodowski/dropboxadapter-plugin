<?php

namespace Renatio\DropboxAdapter\Tests;

use Illuminate\Support\Facades\Cache;
use Renatio\DropboxAdapter\Models\Settings;

abstract class TestCase extends OctoberPestTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function createDropboxConfig(array $overrides = []): array
    {
        return array_merge([
            'driver' => 'dropbox',
            'authorization_token' => 'test-token-' . uniqid(),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function configureDropboxDisk(string $name = 'test_dropbox', array $config = []): void
    {
        config()->set("filesystems.disks.{$name}", $this->createDropboxConfig($config));
    }

    /**
     * October caches settings in a static property and in the cache store, neither of
     * which is undone by the transaction rollback between tests.
     */
    public function forgetSettings(): void
    {
        Settings::clearInternalCache();

        Cache::flush();
    }
}
