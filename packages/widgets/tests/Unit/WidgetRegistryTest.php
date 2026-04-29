<?php

declare(strict_types=1);

use Arqel\Widgets\Tests\Fixtures\CounterWidget;
use Arqel\Widgets\WidgetRegistry;

it('starts empty', function (): void {
    expect((new WidgetRegistry)->all())->toBe([]);
});

it('registers a widget class under a string type', function (): void {
    $registry = new WidgetRegistry;
    $registry->register('counter', CounterWidget::class);

    expect($registry->has('counter'))->toBeTrue()
        ->and($registry->get('counter'))->toBe(CounterWidget::class);
});

it('all() returns the full type → class map', function (): void {
    $registry = new WidgetRegistry;
    $registry->register('counter', CounterWidget::class);

    expect($registry->all())->toBe(['counter' => CounterWidget::class]);
});

it('has() returns false for unknown types and get() returns null', function (): void {
    $registry = new WidgetRegistry;

    expect($registry->has('nope'))->toBeFalse()
        ->and($registry->get('nope'))->toBeNull();
});

it('clear() drops every registered type', function (): void {
    $registry = new WidgetRegistry;
    $registry->register('counter', CounterWidget::class);
    $registry->clear();

    expect($registry->all())->toBe([]);
});

it('throws InvalidArgumentException when the class does not extend Widget', function (): void {
    $registry = new WidgetRegistry;

    expect(fn () => $registry->register('bogus', stdClass::class))
        ->toThrow(InvalidArgumentException::class);
});
