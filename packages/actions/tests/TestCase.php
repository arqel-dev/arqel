<?php

declare(strict_types=1);

namespace Arqel\Actions\Tests;

use Arqel\Actions\ActionsServiceProvider;
use Arqel\Core\ArqelServiceProvider;
use Arqel\Fields\FieldServiceProvider;
use Arqel\Form\FormServiceProvider;
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
            FormServiceProvider::class,
            ActionsServiceProvider::class,
        ];
    }
}
