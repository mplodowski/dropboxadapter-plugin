<?php

use Renatio\DropboxAdapter\Plugin;
use Renatio\DropboxAdapter\Providers\DropboxServiceProvider;
use System\Classes\PluginBase;

describe('Plugin Class', function () {
    describe('Class Structure', function () {
        it('extends PluginBase', function () {
            $reflection = new ReflectionClass(Plugin::class);

            expect($reflection->getParentClass()->getName())->toBe(PluginBase::class);
        });

        it('has pluginDetails method', function () {
            expect(method_exists(Plugin::class, 'pluginDetails'))->toBeTrue();
        });

        it('has boot method', function () {
            expect(method_exists(Plugin::class, 'boot'))->toBeTrue();
        });
    });

    describe('Plugin Details', function () {
        it('returns an array', function () {
            $plugin = new Plugin(app());

            expect($plugin->pluginDetails())->toBeArray();
        });

        it('has required keys', function (string $key) {
            $plugin = new Plugin(app());
            $details = $plugin->pluginDetails();

            expect($details)->toHaveKey($key);
        })->with(['name', 'description', 'author', 'icon', 'homepage']);

        it('uses dropbox icon', function () {
            $plugin = new Plugin(app());
            $details = $plugin->pluginDetails();

            expect($details['icon'])->toBe('icon-dropbox');
        });

        it('has Renatio as author', function () {
            $plugin = new Plugin(app());
            $details = $plugin->pluginDetails();

            expect($details['author'])->toBe('Renatio');
        });

        it('uses language strings for name and description', function () {
            $plugin = new Plugin(app());
            $details = $plugin->pluginDetails();

            expect($details['name'])->toStartWith('renatio.dropboxadapter::lang.plugin.');
            expect($details['description'])->toStartWith('renatio.dropboxadapter::lang.plugin.');
        });
    });

    describe('Boot Method', function () {
        it('registers DropboxServiceProvider', function () {
            $plugin = new Plugin(app());

            $plugin->boot();

            $providers = app()->getLoadedProviders();

            expect($providers)->toHaveKey(DropboxServiceProvider::class);
        });
    });
});
