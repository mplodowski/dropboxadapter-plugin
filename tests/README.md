# Dropbox Adapter Plugin Tests

## Running Tests

From the plugin directory:

```bash
cd plugins/renatio/dropboxadapter
../../../vendor/bin/pest --configuration=phpunit.xml
```

From the project root:

```bash
vendor/bin/pest plugins/renatio/dropboxadapter/tests -c plugins/renatio/dropboxadapter/phpunit.xml
```

Filter a single group or test:

```bash
../../../vendor/bin/pest --configuration=phpunit.xml --filter=RefreshTokenProvider
```

## Test Structure

### Unit Tests

- **PluginTest** - Plugin details and service provider registration
- **Classes/RefreshTokenProviderTest** - OAuth2 token exchange, caching and renewal against a mocked Dropbox endpoint
- **Providers/DropboxServiceProviderTest** - Driver registration, credential validation and auth mode selection

### Integration Tests

- **PluginRegistrationTest** - Plugin registration within October CMS
