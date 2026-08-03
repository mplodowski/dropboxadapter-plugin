<?php

namespace Renatio\DropboxAdapter\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery;
use October\Rain\Database\Model;
use October\Rain\Database\Pivot;
use October\Tests\Concerns\InteractsWithAuthentication;
use October\Tests\Concerns\PerformsMigrations;
use October\Tests\Concerns\PerformsRegistrations;
use Throwable;

/**
 * Base test case for Pest tests in October CMS plugins.
 *
 * This class extends Laravel's TestCase and integrates October CMS
 * functionality through traits. It avoids extending PluginTestCase
 * to maintain compatibility with Pest's setUp/tearDown visibility.
 *
 * Key features:
 * - Single migration: Database migrations run only once per test suite
 * - Transaction isolation: Each test runs inside a transaction that rolls back
 * - PDO connection caching: In-memory SQLite PDO connection is cached and reused
 * - Plugin loading preserved: Plugins load for every test to ensure validation rules are registered
 */
abstract class OctoberPestTestCase extends \TestCase
{
    use InteractsWithAuthentication;
    use PerformsMigrations;
    use PerformsRegistrations;

    /**
     * Cached list of model classes to flush event listeners.
     * Discovered once and reused to avoid expensive reflection on every test.
     */
    protected static ?array $cachedModelClasses = null;

    /**
     * Flag to track if database has been migrated (runs once per test suite).
     */
    protected static bool $databaseMigrated = false;

    /**
     * Cached PDO connection for in-memory SQLite database.
     */
    protected static ?\PDO $inMemoryConnection = null;

    /**
     * Flag to track if plugins have been loaded.
     */
    protected static bool $pluginsLoaded = false;

    public function setUpOctoberPlugin(): void
    {
        // Always load plugins (registers validation rules, event listeners, etc.)
        $this->loadAllPlugins();

        // Restore in-memory database connection if available
        if (self::$inMemoryConnection !== null) {
            DB::connection()->setPdo(self::$inMemoryConnection);
        }

        // Run migrations only once
        if (! self::$databaseMigrated) {
            $this->migrateDatabase();

            self::$databaseMigrated = true;

            // Cache the PDO connection for in-memory SQLite
            if ($this->usingInMemoryDatabase()) {
                self::$inMemoryConnection = DB::connection()->getPdo();
            }
        }

        // Start a database transaction for this test
        $this->beginDatabaseTransaction();

        Model::unguard();

        Mail::pretend();
    }

    public function tearDownOctoberPlugin(): void
    {
        if (class_exists(Mockery::class)) {
            Mockery::close();
        }

        // Rollback the transaction to restore database state
        $this->rollbackDatabaseTransaction();

        $this->flushModelEventListeners();
    }

    /**
     * Determine if we're using an in-memory SQLite database.
     */
    protected function usingInMemoryDatabase(): bool
    {
        return config('database.connections.' . config('database.default') . '.database') === ':memory:';
    }

    /**
     * Begin a database transaction for the test.
     */
    protected function beginDatabaseTransaction(): void
    {
        $connection = DB::connection();

        // Only begin transaction if not already in one
        if ($connection->getPdo()->inTransaction()) {
            return;
        }

        // Disable event dispatcher during transaction management
        $dispatcher = $connection->getEventDispatcher();
        $connection->unsetEventDispatcher();

        $connection->beginTransaction();

        $connection->setEventDispatcher($dispatcher);
    }

    /**
     * Rollback the database transaction after the test.
     */
    protected function rollbackDatabaseTransaction(): void
    {
        $connection = DB::connection();

        // Disable event dispatcher during transaction management
        $dispatcher = $connection->getEventDispatcher();
        $connection->unsetEventDispatcher();

        try {
            // Only rollback if we're in a transaction
            if ($connection->getPdo() && $connection->getPdo()->inTransaction()) {
                $connection->rollBack();
            }
        } catch (Throwable) {
            // If rollback fails, mark database as needing re-migration
            self::$databaseMigrated = false;
        }

        $connection->setEventDispatcher($dispatcher);
    }

    protected function flushModelEventListeners(): void
    {
        // Build cache of model classes on first call (expensive operation done once)
        if (self::$cachedModelClasses === null) {
            self::$cachedModelClasses = $this->discoverModelClasses();
        }

        // Flush only cached model classes (fast operation)
        foreach (self::$cachedModelClasses as $class) {
            try {
                $class::flushEventListeners();
            } catch (Throwable) {
                continue;
            }
        }

        Model::flushEventListeners();
    }

    /**
     * Discover all Model subclasses from declared classes.
     * This expensive operation is only done once and cached.
     *
     * @return array<class-string>
     */
    protected function discoverModelClasses(): array
    {
        $modelClasses = [];

        foreach (get_declared_classes() as $class) {
            if ($class === Pivot::class || str_starts_with($class, 'Mockery')) {
                continue;
            }

            try {
                $reflectClass = new \ReflectionClass($class);

                if (
                    ! $reflectClass->isInstantiable() ||
                    ! $reflectClass->isSubclassOf(Model::class) ||
                    $reflectClass->isSubclassOf(Pivot::class)
                ) {
                    continue;
                }

                if (method_exists($class, 'flushEventListeners')) {
                    $modelClasses[] = $class;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $modelClasses;
    }

    /**
     * Guess the plugin code from the test file location.
     */
    protected function guessPluginCodeFromTest(): string
    {
        return 'Renatio.DropboxAdapter';
    }
}
