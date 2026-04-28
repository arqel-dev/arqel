<?php

declare(strict_types=1);

use Arqel\Table\TableServiceProvider;
use Illuminate\Foundation\Application;

it('boots the table service provider in a Testbench app', function (): void {
    expect(app())->toBeInstanceOf(Application::class)
        ->and(app()->getProviders(TableServiceProvider::class))->not->toBeEmpty();
});

it('autoloads the Arqel\\Table namespace', function (): void {
    expect(class_exists(TableServiceProvider::class))->toBeTrue();
});
