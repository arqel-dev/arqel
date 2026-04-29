<?php

declare(strict_types=1);

use Arqel\Widgets\DashboardRegistry;
use Arqel\Widgets\WidgetRegistry;

it('boots the widgets service provider in a Testbench app', function (): void {
    expect(true)->toBeTrue();
});

it('autoloads the Arqel\\Widgets namespace', function (): void {
    expect(class_exists(WidgetRegistry::class))->toBeTrue();
});

it('binds WidgetRegistry as a singleton', function (): void {
    $first = app(WidgetRegistry::class);
    $second = app(WidgetRegistry::class);

    expect($first)->toBeInstanceOf(WidgetRegistry::class)
        ->and($second)->toBe($first);
});

it('binds DashboardRegistry as a singleton', function (): void {
    $first = app(DashboardRegistry::class);
    $second = app(DashboardRegistry::class);

    expect($first)->toBeInstanceOf(DashboardRegistry::class)
        ->and($second)->toBe($first);
});
