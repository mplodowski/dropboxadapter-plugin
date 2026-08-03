# Dropbox Adapter Plugin

Dropbox v2 API filesystem adapter plugin for OctoberCMS.

> **Important note:** Currently October does not support using Dropbox for `media` and `uploads` disks.
> This plugin's sole purpose is to allow using Dropbox as a storage option for [BackupManager plugin](https://octobercms.com/plugin/renatio-backupmanager).

## Installation

There are couple ways to install this plugin.

1. Use October [Marketplace](http://octobercms.com/help/site/marketplace) and __Add to project__ button.
2. Use October backend area *Settings > System > Updates & Plugins > Install Plugins* and type __Renatio.DropboxAdapter__.
3. Use `php artisan plugin:install Renatio.DropboxAdapter` command.
4. Use `composer require renatio/dropboxadapter-plugin` in project root.

## Configuration

Create a Dropbox app in the [App Console](https://www.dropbox.com/developers/apps). The plugin supports two ways of
authenticating, configured as a filesystem disk in the `disks` array in `config/filesystems.php`.

### Refresh token (recommended)

Dropbox is phasing out long-lived access tokens in favour of short-lived ones. With a refresh token the plugin obtains
a fresh access token automatically and renews it when Dropbox rejects the current one, so the disk keeps working
without manual intervention.

```php
'dropbox' => [
    'driver' => 'dropbox',
    'app_key' => env('DROPBOX_APP_KEY'),
    'app_secret' => env('DROPBOX_APP_SECRET'),
    'refresh_token' => env('DROPBOX_REFRESH_TOKEN'),
],
```

Access tokens are cached using the application cache, so they are not re-fetched on every request.

### Access token (legacy)

Generated in the App Console. These tokens are long-lived but Dropbox no longer issues them for new apps.

```php
'dropbox' => [
    'driver' => 'dropbox',
    'authorization_token' => env('DROPBOX_ACCESS_TOKEN'),
],
```

Set the corresponding values in your `.env` file. If both are configured, the refresh token takes precedence. A disk
configured with incomplete credentials throws an `InvalidArgumentException` naming the missing keys.

### Backend settings

Credentials can also be entered in *Settings > System > Dropbox Adapter*, which avoids editing configuration files.
Values in `config/filesystems.php` always take precedence — a disk only falls back to the backend settings when it
defines no credentials of its own, so existing installations are unaffected.

The settings page has a **Test connection** button that verifies the credentials against the Dropbox API without
writing any files. Secrets are encrypted before being stored.

### Optional settings

Any additional Flysystem option can be passed in the disk configuration, for example `root` to scope the disk to a
subfolder:

```php
'root' => '/backups',
```

## Like this plugin?

If you like this plugin, give this plugin a Like or Make donation with [PayPal](https://www.paypal.me/mplodowski).

## My other plugins

Please check my other [plugins](https://octobercms.com/author/Renatio).

## Support

Please use [GitHub Issues Page](https://github.com/mplodowski/backupmanager-plugin-public/issues) to report any issues
with plugin.

> Reviews should not be used for getting support or reporting bugs, if you need support please use the Plugin support link.
