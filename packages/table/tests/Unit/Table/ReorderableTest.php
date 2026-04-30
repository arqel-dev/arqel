<?php

declare(strict_types=1);

use Arqel\Table\Table;

it('is not reorderable by default', function (): void {
    $table = new Table;

    expect($table->isReorderable())->toBeFalse();
    expect($table->getReorderColumn())->toBeNull();
});

it('enables reordering with the conventional position column when called without arguments', function (): void {
    $table = new Table()->reorderable();

    expect($table->isReorderable())->toBeTrue();
    expect($table->getReorderColumn())->toBe('position');
});

it('enables reordering with a custom column name', function (): void {
    $table = new Table()->reorderable('sort_order');

    expect($table->isReorderable())->toBeTrue();
    expect($table->getReorderColumn())->toBe('sort_order');
});

it('disables reordering when null is passed', function (): void {
    $table = new Table()->reorderable('sort_order')->reorderable(null);

    expect($table->isReorderable())->toBeFalse();
    expect($table->getReorderColumn())->toBeNull();
});

it('exposes the reorderable column name in toArray()', function (): void {
    $enabled = new Table()->reorderable('position');
    $custom = new Table()->reorderable('sort_order');
    $disabled = new Table;

    expect($enabled->toArray())->toHaveKey('reorderable', 'position');
    expect($custom->toArray())->toHaveKey('reorderable', 'sort_order');
    expect($disabled->toArray())->toHaveKey('reorderable', null);
});

it('returns self for fluent chaining', function (): void {
    $table = new Table;

    expect($table->reorderable())->toBe($table);
    expect($table->reorderable(null))->toBe($table);
    expect($table->reorderable('custom'))->toBe($table);
});
