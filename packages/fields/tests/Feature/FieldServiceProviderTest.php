<?php

declare(strict_types=1);

use Arqel\Fields\FieldServiceProvider;
use Illuminate\Foundation\Application;

it('boots the field service provider in a Testbench app', function (): void {
    expect(app())->toBeInstanceOf(Application::class)
        ->and(app()->getProviders(FieldServiceProvider::class))->not->toBeEmpty();
});

it('autoloads the Arqel\\Fields namespace', function (): void {
    expect(class_exists(FieldServiceProvider::class))->toBeTrue();
});
