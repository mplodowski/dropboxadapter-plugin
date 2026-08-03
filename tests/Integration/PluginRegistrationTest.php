<?php

use System\Classes\PluginManager;

describe('Plugin Registration', function () {
    describe('October CMS Integration', function () {
        it('registers the plugin with October CMS', function () {
            $pluginManager = PluginManager::instance();

            expect($pluginManager->hasPlugin('Renatio.DropboxAdapter'))->toBeTrue();
        });

        it('can be found by identifier', function () {
            $pluginManager = PluginManager::instance();
            $plugin = $pluginManager->findByIdentifier('Renatio.DropboxAdapter');

            expect($plugin)->not->toBeNull();
        });

        it('boots without errors', function () {
            $pluginManager = PluginManager::instance();
            $plugin = $pluginManager->findByIdentifier('Renatio.DropboxAdapter');

            expect(function () use ($plugin) {
                $plugin->boot();
            })->not->toThrow(Exception::class);
        });
    });

});
