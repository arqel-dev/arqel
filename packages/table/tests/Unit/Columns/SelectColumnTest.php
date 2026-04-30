<?php

declare(strict_types=1);

use Arqel\Table\Columns\SelectColumn;

it('builds a select column with sane defaults', function (): void {
    $column = SelectColumn::make('status');

    expect($column->getType())->toBe('select')
        ->and($column->isEditable())->toBeTrue()
        ->and($column->getDebounce())->toBe(500)
        ->and($column->getRules())->toBe([])
        ->and($column->resolveOptions())->toBe([]);
});

it('persists static options() fluently', function (): void {
    $column = SelectColumn::make('status')->options(['a' => 'Alpha', 'b' => 'Bravo']);

    expect($column)->toBeInstanceOf(SelectColumn::class)
        ->and($column->resolveOptions())->toBe(['a' => 'Alpha', 'b' => 'Bravo']);
});

it('resolves Closure options lazily at toArray() time', function (): void {
    $count = 0;
    $column = SelectColumn::make('status')->options(function () use (&$count) {
        $count++;

        return ['x' => 'X'];
    });

    expect($count)->toBe(0);

    $payload = $column->toArray();

    expect($count)->toBe(1)
        ->and($payload['options'])->toBe(['x' => 'X']);
});

it('degrades non-array Closure return to []', function (): void {
    $column = SelectColumn::make('status')->options(fn () => 'not-an-array');

    expect($column->resolveOptions())->toBe([])
        ->and($column->toArray()['options'])->toBe([]);
});

it('inherits rules / debounce / readonly setters', function (): void {
    $column = SelectColumn::make('status')
        ->rules(['required', 'in:a,b'])
        ->debounce(-100)
        ->readonly();

    expect($column->getRules())->toBe(['required', 'in:a,b'])
        ->and($column->getDebounce())->toBe(0)
        ->and($column->isEditable())->toBeFalse();

    $column->readonly(false);
    expect($column->isEditable())->toBeTrue();
});

it('serialises editable / debounce / rules / options keys', function (): void {
    $payload = SelectColumn::make('status')
        ->options(['a' => 'Alpha'])
        ->rules(['required'])
        ->debounce(250)
        ->toArray();

    expect($payload)->toHaveKeys(['editable', 'debounce', 'rules', 'options'])
        ->and($payload['type'])->toBe('select')
        ->and($payload['editable'])->toBeTrue()
        ->and($payload['debounce'])->toBe(250)
        ->and($payload['rules'])->toBe(['required'])
        ->and($payload['options'])->toBe(['a' => 'Alpha']);
});

it('accepts Closure for readonly()', function (): void {
    $closure = fn ($record) => false;
    $column = SelectColumn::make('status')->readonly($closure);

    expect($column->getReadonly())->toBe($closure);
});
