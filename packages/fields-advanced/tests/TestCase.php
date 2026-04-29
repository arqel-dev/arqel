<?php

declare(strict_types=1);

namespace Arqel\FieldsAdvanced\Tests;

use Arqel\Core\ArqelServiceProvider;
use Arqel\Fields\FieldServiceProvider;
use Arqel\FieldsAdvanced\FieldsAdvancedServiceProvider;
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
            FieldServiceProvider::class,
            FieldsAdvancedServiceProvider::class,
        ];
    }

    /**
     * Run feature tests against an in-memory SQLite connection so we
     * stay isolated from the host filesystem and across test runs.
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
    }
}
