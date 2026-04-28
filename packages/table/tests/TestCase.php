<?php

declare(strict_types=1);

namespace Arqel\Table\Tests;

use Arqel\Core\ArqelServiceProvider;
use Arqel\Table\TableServiceProvider;
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
}
