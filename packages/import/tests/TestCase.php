<?php

declare(strict_types=1);

namespace Arqel\Import\Tests;

use Arqel\Core\ArqelServiceProvider;
use Arqel\Import\ImportServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Boot the core provider alongside import so integration tests can
     * drive resource imports with validation. `arqel-dev/core` is a
     * hard dependency of this package, so it is always available.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ArqelServiceProvider::class,
            ImportServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        /** @var Application $app */
        // The bundled routes (routes/admin.php) run behind the `web`
        // middleware group, whose EncryptCookies middleware needs an app
        // key. Set a deterministic test key so route-level (HTTP) tests
        // can boot.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * Schema for the `import_users` fixture table used by feature tests
     * that persist rows through {@see \Arqel\Import\Jobs\ProcessImportJob}.
     */
    protected function defineDatabaseMigrations(): void
    {
        \Illuminate\Support\Facades\Schema::create('import_users', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
        });
    }
}
