<?php

declare(strict_types=1);

use Arqel\Widgets\Dashboard;
use Arqel\Widgets\Tests\Fixtures\CounterWidget;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;

it('starts empty with default 12 columns', function (): void {
    $dash = Dashboard::make();

    expect($dash->getWidgets())->toBe([])
        ->and($dash->getColumns())->toBe(12);
});

it('widgets() filters non-Widget entries silently', function (): void {
    $dash = Dashboard::make()->widgets([
        new CounterWidget('a'),
        'not-a-widget',
        new CounterWidget('b'),
    ]);

    expect($dash->getWidgets())->toHaveCount(2);
});

it('addWidget appends to the list', function (): void {
    $dash = Dashboard::make()->addWidget(new CounterWidget('a'));
    $dash->addWidget(new CounterWidget('b'));

    expect($dash->getWidgets())->toHaveCount(2);
});

it('columns() clamps to 1..12', function (): void {
    expect(Dashboard::make()->columns(0)->getColumns())->toBe(1)
        ->and(Dashboard::make()->columns(20)->getColumns())->toBe(12)
        ->and(Dashboard::make()->columns(6)->getColumns())->toBe(6);
});

it('canBeSeenBy returns true by default and delegates to Closure when set', function (): void {
    $dash = Dashboard::make();
    expect($dash->canBeSeenBy(null))->toBeTrue();

    $dash->canSee(fn (?Authenticatable $user) => $user !== null);
    expect($dash->canBeSeenBy(null))->toBeFalse()
        ->and($dash->canBeSeenBy(new User))->toBeTrue();
});

it('toArray sorts widgets by sort and filters by canBeSeenBy', function (): void {
    $a = (new CounterWidget('a'))->sort(20);
    $b = (new CounterWidget('b'))->sort(10);
    $c = (new CounterWidget('c'))->canSee(fn () => false);
    $d = new CounterWidget('d'); // no sort — sorts last

    $payload = Dashboard::make()
        ->widgets([$a, $b, $c, $d])
        ->columns(6)
        ->heading('Overview')
        ->description('KPIs')
        ->toArray();

    $names = array_column($payload['widgets'], 'name');
    expect($names)->toBe(['b', 'a', 'd'])
        ->and($payload['columns'])->toBe(6)
        ->and($payload['heading'])->toBe('Overview')
        ->and($payload['description'])->toBe('KPIs');
});

it('toArray honours canBeSeenBy(user) when given a user', function (): void {
    $widget = (new CounterWidget('admin-only'))->canSee(fn (?Authenticatable $user) => $user !== null);
    $dash = Dashboard::make()->widgets([$widget]);

    expect($dash->toArray(null)['widgets'])->toBe([])
        ->and($dash->toArray(new User)['widgets'])->toHaveCount(1);
});
