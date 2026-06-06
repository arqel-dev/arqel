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
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
