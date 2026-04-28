<?php

declare(strict_types=1);

use Arqel\Form\FormServiceProvider;
use Illuminate\Foundation\Application;

it('boots the form service provider in a Testbench app', function (): void {
    expect(app())->toBeInstanceOf(Application::class)
        ->and(app()->getProviders(FormServiceProvider::class))->not->toBeEmpty();
});

it('autoloads the Arqel\\Form namespace', function (): void {
    expect(class_exists(FormServiceProvider::class))->toBeTrue();
});
