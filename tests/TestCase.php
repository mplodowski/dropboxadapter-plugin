<?php

namespace Renatio\DropboxAdapter\Tests;

/**
 * Base test case for DropboxAdapter plugin tests.
 *
 * This class extends OctoberPestTestCase which provides Pest-compatible
 * test setup with October CMS integration.
 */
abstract class TestCase extends OctoberPestTestCase
{
    public function createDropboxConfig(array $overrides = []): array
    {
        return array_merge([
            'driver' => 'dropbox',
            'authorization_token' => 'test-token-' . uniqid(),
        ], $overrides);
    }

    public function configureDropboxDisk(string $name = 'test_dropbox', array $config = []): void
    {
        config()->set("filesystems.disks.{$name}", $this->createDropboxConfig($config));
    }

    public function clearDisk(string $name): void
    {
        config()->set("filesystems.disks.{$name}", null);
    }
}
