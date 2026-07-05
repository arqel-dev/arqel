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
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
