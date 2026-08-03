<?php

use Renatio\DropboxAdapter\Plugin;
use Renatio\DropboxAdapter\Providers\DropboxServiceProvider;
use System\Classes\PluginBase;

function makePlugin(): Plugin
{
    return new Plugin(app());
}

describe('Plugin Class', function () {
    describe('Class Structure', function () {
        it('extends PluginBase', function () {
            expect(makePlugin())->toBeInstanceOf(PluginBase::class);
        });
    });

    describe('Plugin Details', function () {
        it('has required keys', function (string $key) {
            expect(makePlugin()->pluginDetails())->toHaveKey($key);
        })->with(['name', 'description', 'author', 'icon', 'homepage']);

        it('uses dropbox icon', function () {
            expect(makePlugin()->pluginDetails()['icon'])->toBe('icon-dropbox');
        });

        it('has Renatio as author', function () {
            expect(makePlugin()->pluginDetails()['author'])->toBe('Renatio');
        });

        it('resolves translated name and description', function () {
            $details = makePlugin()->pluginDetails();

            expect(trans($details['name']))->not->toBe($details['name'])
                ->and(trans($details['description']))->not->toBe($details['description']);
        });
    });

    describe('Boot Method', function () {
        it('registers DropboxServiceProvider', function () {
            makePlugin()->boot();

            expect(app()->getLoadedProviders())->toHaveKey(DropboxServiceProvider::class);
        });
    });
});
