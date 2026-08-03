# Dropbox Adapter Plugin Tests

## Running Tests

```bash
php vendor/bin/pest plugins/renatio/dropboxadapter/tests -c plugins/renatio/dropboxadapter/phpunit.xml -p
```

## Test Structure

### Unit Tests

- **PluginTest** - Tests plugin registration and boot functionality
- **Providers/DropboxServiceProviderTest** - Tests Dropbox filesystem service provider registration

### Integration Tests

- **PluginRegistrationTest** - Tests plugin registration within October CMS
