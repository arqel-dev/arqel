<?php

declare(strict_types=1);

use Arqel\Table\Table;

it('defaults the pagination type to lengthAware', function (): void {
    $table = new Table;

    expect($table->getPaginationType())->toBe(Table::PAGINATION_LENGTH_AWARE);
    expect($table->getPaginationType())->toBe('lengthAware');
});

it('flips the pagination type to infinite when configured', function (): void {
    $table = new Table()->paginationType(Table::PAGINATION_INFINITE);

    expect($table->getPaginationType())->toBe('infinite');
});

it('flips the pagination type to cursor when configured', function (): void {
    $table = new Table()->paginationType(Table::PAGINATION_CURSOR);

    expect($table->getPaginationType())->toBe('cursor');
});

it('flips the pagination type to simple when configured', function (): void {
    $table = new Table()->paginationType(Table::PAGINATION_SIMPLE);

    expect($table->getPaginationType())->toBe('simple');
});

it('falls back to lengthAware defensively for unknown values', function (): void {
    $table = new Table()->paginationType('invalid');

    expect($table->getPaginationType())->toBe(Table::PAGINATION_LENGTH_AWARE);
});

it('also falls back to lengthAware when an empty string is passed', function (): void {
    $table = new Table()->paginationType('');

    expect($table->getPaginationType())->toBe(Table::PAGINATION_LENGTH_AWARE);
});

it('exposes the pagination type in the toArray payload under config', function (): void {
    $default = new Table()->toArray();
    $infinite = new Table()->paginationType('infinite')->toArray();
    $cursor = new Table()->paginationType('cursor')->toArray();

    expect($default)->toHaveKey('config');
    expect($default['config'])->toHaveKey('paginationType', 'lengthAware');
    expect($infinite['config'])->toHaveKey('paginationType', 'infinite');
    expect($cursor['config'])->toHaveKey('paginationType', 'cursor');
});

it('exposes the four pagination constants', function (): void {
    expect(Table::PAGINATION_LENGTH_AWARE)->toBe('lengthAware');
    expect(Table::PAGINATION_SIMPLE)->toBe('simple');
    expect(Table::PAGINATION_CURSOR)->toBe('cursor');
    expect(Table::PAGINATION_INFINITE)->toBe('infinite');
});

it('returns self from paginationType for fluent chaining', function (): void {
    $table = new Table;

    expect($table->paginationType('cursor'))->toBe($table);
});
