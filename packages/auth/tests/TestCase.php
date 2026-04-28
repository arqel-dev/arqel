<?php

declare(strict_types=1);

namespace Arqel\Auth\Tests;

use Arqel\Auth\AuthServiceProvider;
use Arqel\Core\ArqelServiceProvider;
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
            AuthServiceProvider::class,
        ];
    }
}
