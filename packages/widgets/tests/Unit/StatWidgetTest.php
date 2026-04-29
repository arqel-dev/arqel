<?php

declare(strict_types=1);

use Arqel\Widgets\StatWidget;

it('exposes type=stat and component=StatWidget', function (): void {
    $widget = StatWidget::make('total_users');

    expect($widget->getType())->toBe('stat')
        ->and($widget->getComponent())->toBe('StatWidget');
});

it('value() accepts scalar and emits it inline in data()', function (): void {
    $widget = StatWidget::make('users')->value(42);

    expect($widget->data()['value'])->toBe(42);
});

it('value() accepts Closure resolved at data() time', function (): void {
    $widget = StatWidget::make('users')->value(fn () => 99);

    expect($widget->data()['value'])->toBe(99);
});

it('value() coerces non-scalar Closure return to null', function (): void {
    $widget = StatWidget::make('users')->value(fn () => ['array']);

    expect($widget->data()['value'])->toBeNull();
});

it('statDescription() handles string and Closure', function (): void {
    expect(
        StatWidget::make('a')->statDescription('static line')->data()['description'],
    )->toBe('static line');

    expect(
        StatWidget::make('b')->statDescription(fn () => '+12% vs last week')->data()['description'],
    )->toBe('+12% vs last week');
});

it('statDescription() Closure non-string returns null', function (): void {
    $widget = StatWidget::make('x')->statDescription(fn () => 42);

    expect($widget->data()['description'])->toBeNull();
});

it('color() honours canonical palette and falls back to primary on unknown', function (): void {
    expect(StatWidget::make('a')->color('success')->data()['color'])->toBe('success')
        ->and(StatWidget::make('b')->color('danger')->data()['color'])->toBe('danger')
        ->and(StatWidget::make('c')->color('rainbow')->data()['color'])->toBe('primary');
});

it('color() accepts the 6 canonical values via constants', function (): void {
    foreach ([
        StatWidget::COLOR_PRIMARY,
        StatWidget::COLOR_SECONDARY,
        StatWidget::COLOR_SUCCESS,
        StatWidget::COLOR_WARNING,
        StatWidget::COLOR_DANGER,
        StatWidget::COLOR_INFO,
    ] as $color) {
        expect(StatWidget::make('x')->color($color)->data()['color'])->toBe($color);
    }
});

it('chart() filters non-numeric points silently', function (): void {
    $widget = StatWidget::make('x')->chart([10, 'bad', 15.5, null, 20]);

    expect($widget->data()['chart'])->toBe([10, 15.5, 20]);
});

it('chart() accepts a Closure', function (): void {
    $widget = StatWidget::make('x')->chart(fn () => [1, 2, 3, 4, 5]);

    expect($widget->data()['chart'])->toBe([1, 2, 3, 4, 5]);
});

it('chart() returns null when not configured', function (): void {
    expect(StatWidget::make('x')->data()['chart'])->toBeNull();
});

it('chart() Closure returning non-array yields null', function (): void {
    $widget = StatWidget::make('x')->chart(fn () => 'broken');

    expect($widget->data()['chart'])->toBeNull();
});

it('url() and icon() / descriptionIcon() pass through to data()', function (): void {
    $widget = StatWidget::make('users')
        ->url('/admin/users')
        ->icon('users')
        ->descriptionIcon('trending-up');

    $data = $widget->data();
    expect($data['url'])->toBe('/admin/users')
        ->and($data['icon'])->toBe('users')
        ->and($data['descriptionIcon'])->toBe('trending-up');
});

it('toArray() embeds the data shape under the canonical Widget envelope', function (): void {
    $payload = StatWidget::make('total_users')
        ->heading('Total Users')
        ->value(150)
        ->color('success')
        ->toArray();

    expect($payload['type'])->toBe('stat')
        ->and($payload['component'])->toBe('StatWidget')
        ->and($payload['heading'])->toBe('Total Users')
        ->and($payload['data']['value'])->toBe(150)
        ->and($payload['data']['color'])->toBe('success');
});

it('deferred() emits data: null at the envelope level', function (): void {
    $payload = StatWidget::make('expensive')->value(fn () => 1)->deferred()->toArray();

    expect($payload['deferred'])->toBeTrue()
        ->and($payload['data'])->toBeNull();
});

it('Closures are not invoked when the widget is deferred (data() is skipped)', function (): void {
    $invoked = false;
    $widget = StatWidget::make('expensive')
        ->value(function () use (&$invoked): int {
            $invoked = true;

            return 1;
        })
        ->deferred();

    $widget->toArray();

    expect($invoked)->toBeFalse();
});
