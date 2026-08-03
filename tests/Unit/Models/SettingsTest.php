<?php

use October\Rain\Exception\ValidationException;
use Renatio\DropboxAdapter\Models\Settings;

describe('Settings', function () {
    it('returns refresh credentials when that mode is selected', function () {
        Settings::set([
            'auth_mode' => 'refresh_token',
            'app_key' => 'stored-key',
            'app_secret' => 'stored-secret',
            'refresh_token' => 'stored-refresh',
        ]);

        expect(Settings::credentials())->toBe([
            'app_key' => 'stored-key',
            'app_secret' => 'stored-secret',
            'refresh_token' => 'stored-refresh',
        ]);
    });

    it('returns only the access token when that mode is selected', function () {
        Settings::set([
            'auth_mode' => 'authorization_token',
            'authorization_token' => 'stored-token',
            'app_key' => 'stored-key',
        ]);

        expect(Settings::credentials())->toBe(['authorization_token' => 'stored-token']);
    });

    it('returns decrypted secrets, not the stored ciphertext', function () {
        Settings::set([
            'auth_mode' => 'refresh_token',
            'app_key' => 'stored-key',
            'app_secret' => 'plain-secret',
            'refresh_token' => 'plain-refresh',
        ]);

        $credentials = Settings::credentials();

        $stored = DB::table('system_settings')
            ->where('item', 'renatio_dropboxadapter_settings')
            ->value('value');

        expect($credentials['app_secret'])->toBe('plain-secret')
            ->and($credentials['refresh_token'])->toBe('plain-refresh')
            ->and($stored)->not->toContain('plain-secret');
    });

    it('returns nothing when unconfigured', function () {
        expect(Settings::credentials())->toBe([]);
    });

    it('allows saving nothing at all, since disks may configure themselves', function () {
        $settings = Settings::instance();
        $settings->auth_mode = 'refresh_token';

        expect($settings->save())->toBeTrue();
    });

    it('rejects a half filled refresh credential set', function () {
        $settings = Settings::instance();
        $settings->auth_mode = 'refresh_token';
        $settings->app_key = 'stored-key';

        expect(fn () => $settings->save())->toThrow(ValidationException::class);
    });

    it('encrypts secrets at rest', function () {
        Settings::set([
            'auth_mode' => 'refresh_token',
            'app_key' => 'stored-key',
            'app_secret' => 'super-secret',
            'refresh_token' => 'super-refresh',
        ]);

        $stored = DB::table('system_settings')
            ->where('item', 'renatio_dropboxadapter_settings')
            ->value('value');

        expect($stored)->not->toContain('super-secret')
            ->and($stored)->not->toContain('super-refresh')
            ->and($stored)->toContain('stored-key');
    });
});
