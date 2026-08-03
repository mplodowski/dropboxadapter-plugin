<?php

return [
    'plugin' => [
        'name' => 'Dropbox Adapter Plugin',
        'description' => 'Dropbox filesystem adapter for October CMS.',
    ],
    'permissions' => [
        'tab' => 'Dropbox Adapter',
        'access_settings' => 'Manage Dropbox settings',
    ],
    'settings' => [
        'label' => 'Dropbox Adapter',
        'description' => 'Configure Dropbox credentials used by filesystem disks.',
        'category' => 'System',
        'config_notice' => 'Credentials defined in config/filesystems.php take precedence over the values below. These settings are only used for disks that do not define their own credentials.',
        'auth_mode_refresh' => 'Refresh token (recommended)',
        'auth_mode_token' => 'Access token (legacy)',
        'test_connection' => 'Test connection',
        'testing' => 'Contacting Dropbox...',
        'connection_ok' => 'Connected as :account',
        'invalid_credentials' => 'Dropbox rejected the credentials.',
        'unknown_account' => 'unknown account',
        'disk_missing' => 'Filesystem disk [:disk] is not configured.',
        'incomplete_refresh_credentials' => 'Provide the app key, app secret and refresh token together, or leave all three empty.',
    ],
    'field' => [
        'auth_mode' => 'Authentication method',
        'app_key' => 'App key',
        'app_secret' => 'App secret',
        'refresh_token' => 'Refresh token',
        'authorization_token' => 'Access token',
    ],
    'comment' => [
        'auth_mode' => 'Dropbox is phasing out long-lived access tokens. Use a refresh token so the access token renews automatically.',
        'refresh_token' => 'Generated once through the Dropbox OAuth2 flow with token_access_type=offline.',
        'authorization_token' => 'Long-lived token generated in the Dropbox App Console. No longer issued for new apps.',
        'test_connection' => 'Verifies the credentials against the Dropbox API without writing any files.',
    ],
    'exception' => [
        'missing_credentials' => 'Dropbox disk is missing credentials. Provide either an [authorization_token], or [app_key], [app_secret] and [refresh_token] for OAuth2 refresh tokens.',
        'incomplete_credentials' => 'Dropbox disk is missing [:keys] required for OAuth2 refresh tokens.',
    ],
];
