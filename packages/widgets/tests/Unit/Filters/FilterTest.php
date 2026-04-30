<?php

declare(strict_types=1);

use Arqel\Widgets\Filters\DateRangeFilter;
use Arqel\Widgets\Filters\SelectFilter;

it('DateRangeFilter::make + label + defaultRange produces canonical toArray shape', function (): void {
    $from = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $to = new DateTimeImmutable('2026-01-31T23:59:59+00:00');

    $payload = DateRangeFilter::make('period')
        ->label('Period')
        ->defaultRange($from, $to)
        ->toArray();

    expect($payload)->toBe([
        'name' => 'period',
        'type' => 'date_range',
        'component' => 'DateRangeFilter',
        'label' => 'Period',
        'default' => ['from' => $from, 'to' => $to],
    ]);
});

it('DateRangeFilter::defaultRange accepts null endpoints', function (): void {
    $payload = DateRangeFilter::make('period')->defaultRange(null, null)->toArray();

    expect($payload['default'])->toBe(['from' => null, 'to' => null]);
});

it('SelectFilter with static options + multiple flag produces canonical toArray shape', function (): void {
    $payload = SelectFilter::make('segment')
        ->label('Segment')
        ->options(['a' => 'A', 'b' => 'B'])
        ->multiple(false)
        ->default('a')
        ->toArray();

    expect($payload)->toBe([
        'name' => 'segment',
        'type' => 'select',
        'component' => 'SelectFilter',
        'label' => 'Segment',
        'default' => 'a',
        'options' => ['a' => 'A', 'b' => 'B'],
        'multiple' => false,
    ]);
});

it('SelectFilter resolves Closure options at toArray() time', function (): void {
    $invocations = 0;
    $filter = SelectFilter::make('status')
        ->options(function () use (&$invocations): array {
            $invocations++;

            return ['active' => 'Active', 'archived' => 'Archived'];
        })
        ->multiple(true);

    expect($invocations)->toBe(0);

    $payload = $filter->toArray();

    expect($invocations)->toBe(1)
        ->and($payload['options'])->toBe(['active' => 'Active', 'archived' => 'Archived'])
        ->and($payload['multiple'])->toBeTrue();
});

it('SelectFilter Closure returning non-array falls back to []', function (): void {
    $payload = SelectFilter::make('status')
        ->options(fn (): string => 'oops')
        ->toArray();

    expect($payload['options'])->toBe([]);
});

it('Filter without explicit label falls back to title-cased name', function (): void {
    $payload = SelectFilter::make('user_segment')->toArray();

    expect($payload['label'])->toBe('User Segment');
});

it('SelectFilter::multiple() defaults to true when called without args', function (): void {
    $payload = SelectFilter::make('tag')->multiple()->toArray();

    expect($payload['multiple'])->toBeTrue();
});

it('Filter::default stores arbitrary values', function (): void {
    $payload = SelectFilter::make('s')->default('all')->toArray();

    expect($payload['default'])->toBe('all');
});
