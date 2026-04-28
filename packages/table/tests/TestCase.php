<?php

declare(strict_types=1);

namespace Arqel\Table\Tests;

use Arqel\Core\ArqelServiceProvider;
use Arqel\Table\TableServiceProvider;
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
            TableServiceProvider::class,
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
            'foreign_key_constraints' => true,
        ]);
    }
}
