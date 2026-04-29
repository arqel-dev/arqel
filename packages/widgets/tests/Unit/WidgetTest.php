<?php

declare(strict_types=1);

use Arqel\Widgets\Tests\Fixtures\CounterWidget;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;

it('exposes the configured name + type + component', function (): void {
    $widget = new CounterWidget('signups');

    expect($widget->getName())->toBe('signups')
        ->and($widget->getType())->toBe('counter')
        ->and($widget->getComponent())->toBe('CounterWidget');
});

it('id() defaults to <type>:<name>', function (): void {
    expect((new CounterWidget('signups'))->id())->toBe('counter:signups');
});

it('fluent setters return $this', function (): void {
    $widget = new CounterWidget('signups');

    expect($widget->heading('Hello'))->toBe($widget)
        ->and($widget->description('Bla'))->toBe($widget)
        ->and($widget->sort(10))->toBe($widget)
        ->and($widget->columnSpan(4))->toBe($widget)
        ->and($widget->poll(30))->toBe($widget)
        ->and($widget->deferred())->toBe($widget)
        ->and($widget->canSee(fn () => true))->toBe($widget)
        ->and($widget->filters(['status' => 'active']))->toBe($widget);
});

it('columnSpan(int) clamps to ≥ 1', function (): void {
    expect((new CounterWidget('x'))->columnSpan(0)->getColumnSpan())->toBe(1)
        ->and((new CounterWidget('x'))->columnSpan(-5)->getColumnSpan())->toBe(1)
        ->and((new CounterWidget('x'))->columnSpan(6)->getColumnSpan())->toBe(6);
});

it('columnSpan(string) passes through unchanged', function (): void {
    expect((new CounterWidget('x'))->columnSpan('full')->getColumnSpan())->toBe('full')
        ->and((new CounterWidget('x'))->columnSpan('1/2')->getColumnSpan())->toBe('1/2');
});

it('poll(0) and poll(<0) disable polling', function (): void {
    $widget = (new CounterWidget('x'))->poll(0);
    expect($widget->getPollingInterval())->toBeNull();

    $widget = (new CounterWidget('x'))->poll(-1);
    expect($widget->getPollingInterval())->toBeNull();
});

it('poll(int>0) sets the interval', function (): void {
    expect((new CounterWidget('x'))->poll(30)->getPollingInterval())->toBe(30);
});

it('canBeSeenBy returns true by default', function (): void {
    $widget = new CounterWidget('x');

    expect($widget->canBeSeenBy(null))->toBeTrue()
        ->and($widget->canBeSeenBy(new User))->toBeTrue();
});

it('canBeSeenBy delegates to the registered Closure', function (): void {
    $widget = (new CounterWidget('x'))->canSee(fn (?Authenticatable $user) => $user !== null);

    expect($widget->canBeSeenBy(null))->toBeFalse()
        ->and($widget->canBeSeenBy(new User))->toBeTrue();
});

it('toArray emits data inline when not deferred', function (): void {
    $widget = new CounterWidget('signups');
    $widget->value = 42;
    $payload = $widget->toArray();

    expect($payload['type'])->toBe('counter')
        ->and($payload['component'])->toBe('CounterWidget')
        ->and($payload['name'])->toBe('signups')
        ->and($payload['id'])->toBe('counter:signups')
        ->and($payload['deferred'])->toBeFalse()
        ->and($payload['data'])->toBe(['value' => 42])
        ->and($payload['filters'])->toBe([]);
});

it('toArray emits data: null when deferred', function (): void {
    $widget = (new CounterWidget('signups'))->deferred();
    $payload = $widget->toArray();

    expect($payload['deferred'])->toBeTrue()
        ->and($payload['data'])->toBeNull();
});

it('toArray includes pollingInterval when poll() was called', function (): void {
    $payload = (new CounterWidget('x'))->poll(15)->toArray();

    expect($payload['pollingInterval'])->toBe(15);
});

it('toArray includes filters and heading/description', function (): void {
    $payload = (new CounterWidget('x'))
        ->heading('Signups')
        ->description('30-day rolling')
        ->filters(['range' => '30d'])
        ->toArray();

    expect($payload['heading'])->toBe('Signups')
        ->and($payload['description'])->toBe('30-day rolling')
        ->and($payload['filters'])->toBe(['range' => '30d']);
});
