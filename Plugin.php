<?php

namespace Renatio\DropboxAdapter;

use Backend\Widgets\Form;
use Illuminate\Support\Facades\Event;
use Renatio\DropboxAdapter\Classes\ConnectionTester;
use Renatio\DropboxAdapter\Models\Settings;
use Renatio\DropboxAdapter\Providers\DropboxServiceProvider;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name' => 'renatio.dropboxadapter::lang.plugin.name',
            'description' => 'renatio.dropboxadapter::lang.plugin.description',
            'author' => 'Renatio',
            'icon' => 'icon-dropbox',
            'homepage' => 'http://octobercms.com/plugin/renatio-dropboxadapter',
        ];
    }

    public function boot(): void
    {
        $this->app->register(DropboxServiceProvider::class);

        $this->extendSettingsForm();
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label' => 'renatio.dropboxadapter::lang.settings.label',
                'description' => 'renatio.dropboxadapter::lang.settings.description',
                'category' => 'renatio.dropboxadapter::lang.settings.category',
                'icon' => 'icon-dropbox',
                'class' => Settings::class,
                'order' => 500,
                'keywords' => 'dropbox storage backup',
                'permissions' => ['renatio.dropboxadapter.access_settings'],
            ],
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'renatio.dropboxadapter.access_settings' => [
                'label' => 'renatio.dropboxadapter::lang.permissions.access_settings',
                'tab' => 'renatio.dropboxadapter::lang.permissions.tab',
            ],
        ];
    }

    /**
     * Adds the connection test handler to the settings form, so the button in the
     * settings partial has somewhere to send its AJAX request.
     */
    protected function extendSettingsForm(): void
    {
        Event::listen('backend.form.extendFields', function (Form $form) {
            if (! $form->model instanceof Settings || $form->isNested) {
                return;
            }

            $form->getController()->addDynamicMethod(
                'onTestDropboxConnection',
                fn () => $this->renderConnectionTest(post()),
            );
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    protected function renderConnectionTest(array $input): array
    {
        $result = (new ConnectionTester)->test($this->credentialsFromInput($input));

        $cssClass = $result['success'] ? 'text-success' : 'text-danger';

        return [
            'result' => sprintf('<span class="%s">%s</span>', $cssClass, e($result['message'])),
        ];
    }

    /**
     * Tests the credentials currently typed into the form, falling back to the saved
     * ones so an unchanged sensitive field still tests what is stored.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function credentialsFromInput(array $input): array
    {
        $submitted = array_filter(
            array_intersect_key(
                $input['Settings'] ?? [],
                array_flip(['app_key', 'app_secret', 'refresh_token', 'authorization_token']),
            ),
            fn ($value) => is_string($value) && trim($value) !== '',
        );

        return $submitted !== [] ? $submitted : Settings::credentials();
    }
}
