<?php

declare(strict_types=1);

use Arqel\Nav\BreadcrumbsBuilder;
use Arqel\Nav\Navigation;
use Arqel\Nav\NavServiceProvider;
use Illuminate\Foundation\Application;

it('boots the nav service provider in a Testbench app', function (): void {
    expect(app())->toBeInstanceOf(Application::class)
        ->and(app()->getProviders(NavServiceProvider::class))->not->toBeEmpty();
});

it('registers Navigation and BreadcrumbsBuilder as singletons', function (): void {
    expect(app(Navigation::class))->toBe(app(Navigation::class))
        ->and(app(BreadcrumbsBuilder::class))->toBe(app(BreadcrumbsBuilder::class));
});
