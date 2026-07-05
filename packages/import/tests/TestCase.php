<?php

declare(strict_types=1);

namespace Arqel\Import\Tests;

use Arqel\Core\ArqelServiceProvider;
use Arqel\Import\ImportServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Boot the core provider alongside import so integration tests can
     * drive import functionality. `arqel-dev/core` is a
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
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => Fixtures\Models\TestUser::class,
        ]);
    }

    /**
     * Schema for the `import_users` fixture table used by feature tests
     * that persist imported rows (see `Tests\Fixtures\Models\ImportUser`),
     * plus `import_test_users` so Feature/HTTP tests can `actingAs()` a
     * real Authenticatable through the `web + auth` route middleware.
     */
    protected function defineDatabaseMigrations(): void
    {
        Schema::create('import_users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
        });

        Schema::create('import_test_users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->default('user');
            $table->timestamps();
        });
    }
}
