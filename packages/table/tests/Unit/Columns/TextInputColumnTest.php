<?php

declare(strict_types=1);

use Arqel\Table\Columns\TextInputColumn;

it('builds a textInput column with sane defaults', function (): void {
    $column = TextInputColumn::make('name');

    expect($column)->toBeInstanceOf(TextInputColumn::class)
        ->and($column->getType())->toBe('textInput')
        ->and($column->getName())->toBe('name')
        ->and($column->isEditable())->toBeTrue()
        ->and($column->getDebounce())->toBe(500)
        ->and($column->getRules())->toBe([]);
});

it('persists fluent rules() chain', function (): void {
    $column = TextInputColumn::make('name')->rules(['required', 'string']);

    expect($column)->toBeInstanceOf(TextInputColumn::class)
        ->and($column->getRules())->toBe(['required', 'string']);
});

it('accepts positive debounce values', function (): void {
    $column = TextInputColumn::make('name')->debounce(1000);

    expect($column->getDebounce())->toBe(1000);
});

it('clamps negative debounce to 0', function (): void {
    $column = TextInputColumn::make('name')->debounce(-50);

    expect($column->getDebounce())->toBe(0);
});

it('accepts a zero debounce', function (): void {
    $column = TextInputColumn::make('name')->debounce(0);

    expect($column->getDebounce())->toBe(0);
});

it('readonly() flips editable to false and back', function (): void {
    $column = TextInputColumn::make('name');

    expect($column->isEditable())->toBeTrue();

    $column->readonly();

    expect($column->isEditable())->toBeFalse();

    $column->readonly(false);

    expect($column->isEditable())->toBeTrue();
});

it('accepts a Closure for readonly() and stores it', function (): void {
    $closure = fn ($record) => isset($record->status) && $record->status === 'archived';

    $column = TextInputColumn::make('name')->readonly($closure);

    expect($column->getReadonly())->toBe($closure)
        // Closure does not flip editable eagerly — that resolution happens server-side per record.
        ->and($column->isEditable())->toBeTrue();
});

it('serialises editable, debounce and rules in toArray()', function (): void {
    $payload = TextInputColumn::make('name')
        ->rules(['required'])
        ->debounce(750)
        ->toArray();

    expect($payload)->toHaveKeys(['editable', 'debounce', 'rules', 'type', 'name'])
        ->and($payload['type'])->toBe('textInput')
        ->and($payload['editable'])->toBeTrue()
        ->and($payload['debounce'])->toBe(750)
        ->and($payload['rules'])->toBe(['required']);
});

it('reflects readonly flip in toArray()', function (): void {
    $payload = TextInputColumn::make('name')->readonly()->toArray();

    expect($payload['editable'])->toBeFalse();
});
