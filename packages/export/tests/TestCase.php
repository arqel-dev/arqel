<?php

declare(strict_types=1);

namespace Arqel\Export\Tests;

use Arqel\Core\ArqelServiceProvider;
use Arqel\Export\ExportServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Boot the core provider alongside export so integration tests can
     * drive `ResourceController::bulkAction` (it resolves core's
     * `ResourceRegistry` + `InertiaDataBuilder`). `arqel-dev/core` is a
     * hard dependency of this package, so it is always available.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ArqelServiceProvider::class,
            ExportServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        /** @var Application $app */
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    // The suite does not use RefreshDatabase, so spatie's `runsMigrations`
    // auto-run never fires (it only runs through the migrator). This hook is
    // the single source that creates `arqel_exports` for the test DB; it
    // runs once per app boot, so there is no double-load with the dated
    // migration name now registered via `hasMigration()`.
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
