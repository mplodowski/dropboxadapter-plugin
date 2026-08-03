<?php

namespace Renatio\DropboxAdapter\Tests;

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
}
