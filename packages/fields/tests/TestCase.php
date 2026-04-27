<?php

declare(strict_types=1);

namespace Arqel\Fields\Tests;

use Arqel\Core\ArqelServiceProvider;
use Arqel\Fields\FieldServiceProvider;
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
        ];
    }
}
