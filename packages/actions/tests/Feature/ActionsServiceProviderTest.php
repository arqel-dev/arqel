<?php

declare(strict_types=1);

use Arqel\Actions\ActionsServiceProvider;
use Illuminate\Foundation\Application;

it('boots the actions service provider in a Testbench app', function (): void {
    expect(app())->toBeInstanceOf(Application::class)
        ->and(app()->getProviders(ActionsServiceProvider::class))->not->toBeEmpty();
});

it('autoloads the Arqel\\Actions namespace', function (): void {
    expect(class_exists(ActionsServiceProvider::class))->toBeTrue();
});
