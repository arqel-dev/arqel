<?php

declare(strict_types=1);

use Arqel\Widgets\Dashboard;
use Arqel\Widgets\DashboardRegistry;

it('starts empty', function (): void {
    expect((new DashboardRegistry)->all())->toBe([]);
});

it('register stores a dashboard keyed by its id', function (): void {
    $registry = new DashboardRegistry;
    $dashboard = Dashboard::make('main', 'Main');

    $registry->register($dashboard);

    expect($registry->has('main'))->toBeTrue()
        ->and($registry->get('main'))->toBe($dashboard);
});

it('has() returns false for unknown ids and get() returns null', function (): void {
    $registry = new DashboardRegistry;

    expect($registry->has('nope'))->toBeFalse()
        ->and($registry->get('nope'))->toBeNull();
});

it('all() returns the full id → Dashboard map', function (): void {
    $registry = new DashboardRegistry;
    $a = Dashboard::make('a', 'A');
    $b = Dashboard::make('b', 'B');

    $registry->register($a);
    $registry->register($b);

    expect($registry->all())->toBe(['a' => $a, 'b' => $b]);
});

it('clear() empties the registry', function (): void {
    $registry = new DashboardRegistry;
    $registry->register(Dashboard::make('main', 'Main'));
    $registry->clear();

    expect($registry->all())->toBe([]);
});

it('throws InvalidArgumentException on duplicate id', function (): void {
    $registry = new DashboardRegistry;
    $registry->register(Dashboard::make('main', 'Main'));

    expect(fn () => $registry->register(Dashboard::make('main', 'Other')))
        ->toThrow(InvalidArgumentException::class);
});
