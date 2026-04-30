<?php

declare(strict_types=1);

namespace Arqel\Core\Tests;

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
        ];
    }

    /**
     * Run feature/integration tests against an in-memory SQLite
     * connection so we never touch the host filesystem and stay
     * isolated between test runs.
     */
    protected function defineEnvironment($app): void
    {
        /** @var Application $app */
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Stable, deterministic app key so HTTP feature tests can boot
        // the encrypter (cookie middleware, session, etc.) without
        // requiring a `.env` file.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
