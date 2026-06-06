<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests;

use Arqel\Ai\AiServiceProvider;
use Arqel\Core\ArqelServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ArqelServiceProvider::class,
            AiServiceProvider::class,
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
            'foreign_key_constraints' => true,
        ]);
    }

    // NOTE: the `ai_usage` migration is loaded by AiServiceProvider itself
    // (spatie `runsMigrations`), so we deliberately do NOT call
    // `loadMigrationsFrom()` here — doing so would register the same
    // migration twice and the Laravel 12.61+ migrator runs both, raising
    // "table ai_usage already exists". Offline-safety tests drop the table
    // explicitly inside a RefreshDatabase transaction.
}
