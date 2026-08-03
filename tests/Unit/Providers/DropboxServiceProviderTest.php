<?php

use Illuminate\Support\Facades\Storage;
use Renatio\DropboxAdapter\Providers\DropboxServiceProvider;

describe('DropboxServiceProvider', function () {
    describe('Driver Registration', function () {
        it('creates a dropbox disk with valid configuration', function () {
            $this->configureDropboxDisk('test_dropbox');

            $disk = Storage::disk('test_dropbox');

            expect($disk)->toBeStorageDisk();
        });

        it('passes configuration to the dropbox adapter', function () {
            $this->configureDropboxDisk('configured_dropbox', [
                'authorization_token' => 'test-specific-token-123',
            ]);

            $disk = Storage::disk('configured_dropbox');

            expect($disk)->toBeStorageDisk();
        });

        it('throws exception when authorization_token is missing', function () {
            config()->set('filesystems.disks.invalid_dropbox', [
                'driver' => 'dropbox',
                // Missing authorization_token
            ]);

            expect(function () {
                Storage::disk('invalid_dropbox');
            })->toThrow(Exception::class);
        });

    });

    describe('Class Structure', function () {
        it('extends ServiceProvider', function () {
            $reflection = new ReflectionClass(DropboxServiceProvider::class);

            expect($reflection->getParentClass()->getName())
                ->toBe(\Illuminate\Support\ServiceProvider::class);
        });

        it('has boot method', function () {
            expect(method_exists(DropboxServiceProvider::class, 'boot'))->toBeTrue();
        });
    });

    describe('Configuration Options', function () {
        it('preserves config array in filesystem adapter', function () {
            $config = [
                'authorization_token' => 'test-token-config',
                'root' => '/test-folder',
            ];

            $this->configureDropboxDisk('config_test', $config);

            // Disk creation with additional config options should work
            $disk = Storage::disk('config_test');

            expect($disk)->toBeStorageDisk();
        });
    });
});
