# Dropbox Adapter Plugin

Dropbox v2 API filesystem adapter plugin for OctoberCMS.

> **Important note:** Currently October does not support using Dropbox for `media` and `uploads` disks.
> This plugin solely purpose is to allow using Dropbox as storage option for [BackupManager plugin](https://octobercms.com/plugin/renatio-backupmanager).

## Installation

There are couple ways to install this plugin.

1. Use October [Marketplace](http://octobercms.com/help/site/marketplace) and __Add to project__ button.
2. Use October backend area *Settings > System > Updates & Plugins > Install Plugins* and type __Renatio.DropboxAdapter__.
3. Use `php artisan plugin:install Renatio.DropboxAdapter` command.
4. Use `composer require renatio/dropboxadapter-plugin` in project root.

## Configuration

The first thing you need to do is to get an authorization token at Dropbox. A token can be generated in the [App Console](https://www.dropbox.com/developers/apps) for any Dropbox API app. You'll find more info at [the Dropbox Developer Blog](https://blogs.dropbox.com/developers/2014/05/generate-an-access-token-for-your-own-account/).

Add new filesystem disk in `disks` array in `config/filesystems.php`:

```
'dropbox' => [
    'driver' => 'dropbox',
    'authorization_token' => env('DROPBOX_ACCESS_TOKEN'),
],
```

Set generated authorization token `DROPBOX_ACCESS_TOKEN` in your `.env` file.

## Like this plugin?

If you like this plugin, give this plugin a Like or Make donation with [PayPal](https://www.paypal.me/mplodowski).

## My other plugins

Please check my other [plugins](https://octobercms.com/author/Renatio).

## Support

Please use [GitHub Issues Page](https://github.com/mplodowski/backupmanager-plugin-public/issues) to report any issues
with plugin.

> Reviews should not be used for getting support or reporting bugs, if you need support please use the Plugin support link.
