<?php

declare(strict_types=1);

use Arqel\Widgets\CustomWidget;

it('exposes the custom type and the component set via factory', function (): void {
    $widget = CustomWidget::make('onboarding', 'OnboardingProgressWidget');

    expect($widget->getType())->toBe('custom')
        ->and($widget->getComponent())->toBe('OnboardingProgressWidget')
        ->and($widget->getName())->toBe('onboarding');
});

it('component() rejects empty strings', function (): void {
    expect(fn () => CustomWidget::make('x', 'Foo')->component(''))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => CustomWidget::make('x', 'Foo')->component('   '))
        ->toThrow(InvalidArgumentException::class);
});

it('component() updates the React component name', function (): void {
    $widget = CustomWidget::make('x', 'Original')->component('Updated');

    expect($widget->getComponent())->toBe('Updated');
});

it('withData accepts an array payload', function (): void {
    $widget = CustomWidget::make('x', 'Foo')->withData(['answer' => 42]);

    expect($widget->data())->toBe(['answer' => 42]);
});

it('withData resolves Closures lazily', function (): void {
    $invoked = 0;
    $widget = CustomWidget::make('x', 'Foo')->withData(function () use (&$invoked) {
        $invoked++;

        return ['cached' => true];
    });

    expect($invoked)->toBe(0);
    expect($widget->data())->toBe(['cached' => true]);
    expect($invoked)->toBe(1);
});

it('withData Closure returning non-array falls back to empty array', function (): void {
    $widget = CustomWidget::make('x', 'Foo')->withData(fn () => 'oops');

    expect($widget->data())->toBe([]);
});

it('default payload is an empty array', function (): void {
    $widget = CustomWidget::make('x', 'Foo');

    expect($widget->data())->toBe([]);
});

it('inherits heading, sort, columnSpan, and other Widget setters', function (): void {
    $widget = CustomWidget::make('onboarding', 'OnboardingProgressWidget')
        ->heading('Onboarding')
        ->description('Steps remaining')
        ->sort(5)
        ->columnSpan('1/2')
        ->withData(['steps' => 3]);

    $payload = $widget->toArray();

    expect($payload['heading'])->toBe('Onboarding')
        ->and($payload['description'])->toBe('Steps remaining')
        ->and($payload['sort'])->toBe(5)
        ->and($payload['columnSpan'])->toBe('1/2')
        ->and($payload['type'])->toBe('custom')
        ->and($payload['component'])->toBe('OnboardingProgressWidget')
        ->and($payload['data'])->toBe(['steps' => 3]);
});

it('deferred CustomWidget emits data: null and skips the Closure', function (): void {
    $invoked = 0;
    $widget = CustomWidget::make('x', 'Foo')
        ->deferred()
        ->withData(function () use (&$invoked) {
            $invoked++;

            return ['x' => 1];
        });

    $payload = $widget->toArray();

    expect($payload['deferred'])->toBeTrue()
        ->and($payload['data'])->toBeNull()
        ->and($invoked)->toBe(0);
});

it('id() default is custom:<name>', function (): void {
    expect(CustomWidget::make('onboarding', 'Foo')->id())->toBe('custom:onboarding');
});
