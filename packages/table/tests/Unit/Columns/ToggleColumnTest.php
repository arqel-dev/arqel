<?php

declare(strict_types=1);

use Arqel\Table\Columns\ToggleColumn;

it('builds a toggle column with sane defaults', function (): void {
    $column = ToggleColumn::make('is_active');

    expect($column->getType())->toBe('toggle')
        ->and($column->isEditable())->toBeTrue()
        ->and($column->getOnValue())->toBeTrue()
        ->and($column->getOffValue())->toBeFalse();
});

it('persists onValue() and offValue() fluently', function (): void {
    $column = ToggleColumn::make('status')
        ->onValue('active')
        ->offValue('inactive');

    expect($column)->toBeInstanceOf(ToggleColumn::class)
        ->and($column->getOnValue())->toBe('active')
        ->and($column->getOffValue())->toBe('inactive');
});

it('readonly() flips editable to false and back', function (): void {
    $column = ToggleColumn::make('is_active')->readonly();

    expect($column->isEditable())->toBeFalse();

    $column->readonly(false);

    expect($column->isEditable())->toBeTrue();
});

it('accepts a Closure for readonly() and stores it without flipping editable', function (): void {
    $closure = fn ($record) => true;
    $column = ToggleColumn::make('is_active')->readonly($closure);

    expect($column->getReadonly())->toBe($closure)
        ->and($column->isEditable())->toBeTrue();
});

it('serialises onValue, offValue, editable, debounce, rules in toArray()', function (): void {
    $payload = ToggleColumn::make('status')
        ->onValue('on')
        ->offValue('off')
        ->rules(['required'])
        ->debounce(300)
        ->toArray();

    expect($payload)->toHaveKeys(['editable', 'debounce', 'rules', 'onValue', 'offValue'])
        ->and($payload['type'])->toBe('toggle')
        ->and($payload['onValue'])->toBe('on')
        ->and($payload['offValue'])->toBe('off')
        ->and($payload['editable'])->toBeTrue()
        ->and($payload['debounce'])->toBe(300)
        ->and($payload['rules'])->toBe(['required']);
});

it('clamps negative debounce to 0', function (): void {
    $column = ToggleColumn::make('is_active')->debounce(-1);

    expect($column->getDebounce())->toBe(0);
});
