<?php

declare(strict_types=1);

use Arqel\Table\Filters\Constraints\SelectConstraint;
use Illuminate\Database\Eloquent\Builder;

function selectBuilder(): Builder
{
    return Mockery::mock(Builder::class);
}

it('SelectConstraint: default operators and type', function (): void {
    $c = SelectConstraint::make('role_id');

    expect($c->getType())->toBe('select')
        ->and($c->getDefaultOperators())->toBe(['equals', 'not_equals', 'in', 'not_in']);
});

it('SelectConstraint: array options resolved eagerly via toArray', function (): void {
    $c = SelectConstraint::make('role_id')->options([1 => 'Admin', 2 => 'User']);

    expect($c->resolveOptions())->toBe([1 => 'Admin', 2 => 'User'])
        ->and($c->toArray()['options'])->toBe([1 => 'Admin', 2 => 'User']);
});

it('SelectConstraint: closure options resolved at toArray time', function (): void {
    $c = SelectConstraint::make('role_id')->options(fn () => [10 => 'A', 20 => 'B']);

    expect($c->toArray()['options'])->toBe([10 => 'A', 20 => 'B']);
});

it('SelectConstraint: closure returning non-array degrades to []', function (): void {
    $c = SelectConstraint::make('role_id')->options(fn () => 'oops');

    expect($c->resolveOptions())->toBe([]);
});

it('SelectConstraint: in uses whereIn with array values', function (): void {
    $c = SelectConstraint::make('role_id');
    $b = selectBuilder();
    $b->shouldReceive('whereIn')->once()->with('role_id', [1, 2, 3])->andReturnSelf();

    $c->apply($b, 'in', [1, 2, 3]);
});

it('SelectConstraint: not_in uses whereNotIn', function (): void {
    $c = SelectConstraint::make('role_id');
    $b = selectBuilder();
    $b->shouldReceive('whereNotIn')->once()->with('role_id', [4, 5])->andReturnSelf();

    $c->apply($b, 'not_in', [4, 5]);
});

it('SelectConstraint: in with non-array silently no-ops', function (): void {
    $c = SelectConstraint::make('role_id');
    $b = selectBuilder();
    $b->shouldNotReceive('whereIn');

    $c->apply($b, 'in', 'not-an-array');
});
