<?php

use Illuminate\Support\Facades\Storage;
use Renatio\DropboxAdapter\Models\Settings;
use Renatio\DropboxAdapter\Providers\DropboxServiceProvider;
use Spatie\Dropbox\RefreshableTokenProvider;

describe('DropboxServiceProvider', function () {
    describe('Driver Registration', function () {
        it('creates a disk backed by the dropbox adapter', function () {
            $this->configureDropboxDisk('test_dropbox');

            expect(Storage::disk('test_dropbox'))->toBeDropboxDisk();
        });

        it('passes the authorization token to the dropbox client', function () {
            $this->configureDropboxDisk('configured_dropbox', [
                'authorization_token' => 'test-specific-token-123',
            ]);

            $token = dropboxTokenProvider(Storage::disk('configured_dropbox'))->getToken();

            expect($token)->toBe('test-specific-token-123');
        });

        it('gives the dropbox client a retrying http handler', function () {
            $this->configureDropboxDisk('retrying_dropbox');

            expect(dropboxHandlerStack(Storage::disk('retrying_dropbox')))
                ->toContain('dropbox_retry');
        });

        it('applies the root option as a path prefix', function () {
            $this->configureDropboxDisk('prefixed_dropbox', ['root' => '/backups']);

            $disk = Storage::disk('prefixed_dropbox');

            expect($disk->path('file.zip'))->toBe('/backups/file.zip');
        });
    });

    describe('Configuration Validation', function () {
        it('rejects a disk without any credentials', function () {
            config()->set('filesystems.disks.invalid_dropbox', ['driver' => 'dropbox']);

            expect(fn () => Storage::disk('invalid_dropbox'))
                ->toThrow(InvalidArgumentException::class);
        });

        it('rejects an empty authorization token', function () {
            config()->set('filesystems.disks.empty_token', [
                'driver' => 'dropbox',
                'authorization_token' => '',
            ]);

            expect(fn () => Storage::disk('empty_token'))
                ->toThrow(InvalidArgumentException::class);
        });

        it('rejects refresh credentials that are incomplete', function (array $config) {
            config()->set('filesystems.disks.partial_oauth', array_merge(['driver' => 'dropbox'], $config));

            expect(fn () => Storage::disk('partial_oauth'))
                ->toThrow(InvalidArgumentException::class);
        })->with([
            'missing secret' => [['app_key' => 'key', 'refresh_token' => 'refresh']],
            'missing key' => [['app_secret' => 'secret', 'refresh_token' => 'refresh']],
            'missing refresh token' => [['app_key' => 'key', 'app_secret' => 'secret']],
        ]);

        it('names the missing credentials in the error message', function () {
            config()->set('filesystems.disks.partial_disk', [
                'driver' => 'dropbox',
                'app_key' => 'key',
            ]);

            expect(fn () => Storage::disk('partial_disk'))
                ->toThrow(InvalidArgumentException::class, 'app_secret');
        });
    });

    describe('OAuth2 Refresh Tokens', function () {
        it('creates a disk from app credentials and a refresh token', function () {
            config()->set('filesystems.disks.oauth_dropbox', [
                'driver' => 'dropbox',
                'app_key' => 'test-app-key',
                'app_secret' => 'test-app-secret',
                'refresh_token' => 'test-refresh-token',
            ]);

            expect(Storage::disk('oauth_dropbox'))->toBeDropboxDisk();
        });

        it('uses a refreshable token provider when refresh credentials are given', function () {
            config()->set('filesystems.disks.oauth_provider', [
                'driver' => 'dropbox',
                'app_key' => 'test-app-key',
                'app_secret' => 'test-app-secret',
                'refresh_token' => 'test-refresh-token',
            ]);

            $provider = dropboxTokenProvider(Storage::disk('oauth_provider'));

            expect($provider)->toBeInstanceOf(RefreshableTokenProvider::class);
        });

        it('prefers refresh credentials over a static authorization token', function () {
            config()->set('filesystems.disks.both_modes', [
                'driver' => 'dropbox',
                'authorization_token' => 'static-token',
                'app_key' => 'test-app-key',
                'app_secret' => 'test-app-secret',
                'refresh_token' => 'test-refresh-token',
            ]);

            $provider = dropboxTokenProvider(Storage::disk('both_modes'));

            expect($provider)->toBeInstanceOf(RefreshableTokenProvider::class);
        });
    });

    describe('Temporary URLs', function () {
        it('advertises temporary url support', function () {
            $this->configureDropboxDisk('temp_url_disk');

            expect(Storage::disk('temp_url_disk')->providesTemporaryUrls())->toBeTrue();
        });

        it('exposes getTemporaryUrl on the adapter, which is how October detects support', function () {
            $this->configureDropboxDisk('october_detect_disk');

            expect(method_exists(Storage::disk('october_detect_disk')->getAdapter(), 'getTemporaryUrl'))
                ->toBeTrue();
        });

        it('returns null instead of failing when Dropbox refuses the link', function () {
            $this->configureDropboxDisk('temp_error_disk');
            $disk = Storage::disk('temp_error_disk');

            mockDropboxHttp($disk, [new GuzzleHttp\Psr7\Response(409, [], '{"error_summary":"path/not_found/"}')]);

            expect($disk->getAdapter()->getTemporaryUrl('missing.zip'))->toBeNull();
        });

        it('asks Dropbox for a temporary link', function () {
            $this->configureDropboxDisk('temp_link_disk');
            $disk = Storage::disk('temp_link_disk');

            mockDropboxHttp($disk, [
                new GuzzleHttp\Psr7\Response(200, [], json_encode([
                    'link' => 'https://dl.dropboxusercontent.com/apitl/1/generated',
                    'metadata' => ['name' => 'backup.zip'],
                ])),
            ]);

            expect($disk->temporaryUrl('backup.zip', now()->addHour()))
                ->toBe('https://dl.dropboxusercontent.com/apitl/1/generated');
        });

        it('prefixes the configured root when requesting the link', function () {
            $this->configureDropboxDisk('rooted_disk', ['root' => '/backups']);
            $disk = Storage::disk('rooted_disk');

            $history = mockDropboxHttp($disk, [
                new GuzzleHttp\Psr7\Response(200, [], json_encode(['link' => 'https://example.test/x'])),
            ]);

            $disk->temporaryUrl('backup.zip', now()->addHour());

            $payload = json_decode((string) $history[0]['request']->getBody(), true);

            expect($payload['path'])->toBe('/backups/backup.zip');
        });
    });

    describe('Settings Fallback', function () {
        it('uses saved settings when the disk defines no credentials', function () {
            Settings::set([
                'auth_mode' => 'authorization_token',
                'authorization_token' => 'token-from-settings',
            ]);

            config()->set('filesystems.disks.settings_disk', ['driver' => 'dropbox']);

            expect(dropboxTokenProvider(Storage::disk('settings_disk'))->getToken())
                ->toBe('token-from-settings');
        });

        it('lets disk configuration win over saved settings', function () {
            Settings::set([
                'auth_mode' => 'authorization_token',
                'authorization_token' => 'token-from-settings',
            ]);

            config()->set('filesystems.disks.config_wins', [
                'driver' => 'dropbox',
                'authorization_token' => 'token-from-config',
            ]);

            expect(dropboxTokenProvider(Storage::disk('config_wins'))->getToken())
                ->toBe('token-from-config');
        });

        it('ignores settings entirely once the disk defines credentials', function () {
            Settings::set([
                'auth_mode' => 'refresh_token',
                'app_key' => 'key-from-settings',
                'app_secret' => 'secret-from-settings',
                'refresh_token' => 'refresh-from-settings',
            ]);

            config()->set('filesystems.disks.no_mixing', [
                'driver' => 'dropbox',
                'authorization_token' => 'token-from-config',
            ]);

            $provider = dropboxTokenProvider(Storage::disk('no_mixing'));

            expect($provider)->not->toBeInstanceOf(RefreshableTokenProvider::class)
                ->and($provider->getToken())->toBe('token-from-config');
        });

        it('still rejects a disk when neither source has credentials', function () {
            config()->set('filesystems.disks.nothing_configured', ['driver' => 'dropbox']);

            expect(fn () => Storage::disk('nothing_configured'))
                ->toThrow(InvalidArgumentException::class);
        });
    });

    describe('Class Structure', function () {
        it('is registered as a service provider', function () {
            expect(app()->getLoadedProviders())->toHaveKey(DropboxServiceProvider::class);
        });
    });
});
