<?php

namespace Renatio\DropboxAdapter\Models;

use October\Rain\Database\Model;
use October\Rain\Database\Traits\Encryptable;
use October\Rain\Database\Traits\Validation;
use October\Rain\Exception\ValidationException;
use System\Behaviors\SettingsModel;

class Settings extends Model
{
    use Encryptable;
    use Validation;

    public $implement = [SettingsModel::class];

    public $settingsCode = 'renatio_dropboxadapter_settings';

    public $settingsFields = 'fields.yaml';

    protected $encryptable = ['app_secret', 'refresh_token', 'authorization_token'];

    public $rules = [];

    /**
     * Settings are optional, because disks may define their own credentials in
     * config/filesystems.php. Only a partially filled refresh credential set is
     * rejected, since it can never produce a working disk.
     */
    public function beforeValidate(): void
    {
        if ($this->auth_mode !== 'refresh_token') {
            return;
        }

        $filled = array_filter(
            [$this->app_key, $this->app_secret, $this->refresh_token],
            fn ($value) => is_string($value) && trim($value) !== '',
        );

        if ($filled !== [] && count($filled) < 3) {
            throw new ValidationException([
                'app_key' => trans('renatio.dropboxadapter::lang.settings.incomplete_refresh_credentials'),
            ]);
        }
    }

    public function initSettingsData(): void
    {
        $this->auth_mode = 'refresh_token';
    }

    /**
     * Credentials configured here, in the shape the filesystem disk expects.
     *
     * @return array<string, string>
     */
    public static function credentials(): array
    {
        $settings = static::instance();

        $keys = $settings->getSettingsValue('auth_mode') === 'authorization_token'
            ? ['authorization_token']
            : ['app_key', 'app_secret', 'refresh_token'];

        $credentials = [];

        foreach ($keys as $key) {
            $credentials[$key] = $settings->readCredential($key);
        }

        return array_filter($credentials, fn ($value) => is_string($value) && trim($value) !== '');
    }

    /**
     * Encrypted values are only decrypted through attribute access, which in turn only
     * sees keys present in the attributes array.
     */
    protected function readCredential(string $key): mixed
    {
        if (! array_key_exists($key, $this->attributes)) {
            $this->attributes[$key] = $this->getSettingsValue($key);
        }

        return $this->{$key};
    }

    public function getAuthModeOptions(): array
    {
        return [
            'refresh_token' => 'renatio.dropboxadapter::lang.settings.auth_mode_refresh',
            'authorization_token' => 'renatio.dropboxadapter::lang.settings.auth_mode_token',
        ];
    }
}
