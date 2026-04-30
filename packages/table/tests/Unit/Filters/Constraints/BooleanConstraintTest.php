<?php

declare(strict_types=1);

use Arqel\Table\Filters\Constraints\BooleanConstraint;
use Illuminate\Database\Eloquent\Builder;

function boolBuilder(): Builder
{
    return Mockery::mock(Builder::class);
}

it('BooleanConstraint: default operators and type', function (): void {
    $c = BooleanConstraint::make('is_active');

    expect($c->getType())->toBe('boolean')
        ->and($c->getDefaultOperators())->toBe(['is_true', 'is_false']);
});

it('BooleanConstraint: is_true applies where(field, true)', function (): void {
    $c = BooleanConstraint::make('is_active');
    $b = boolBuilder();
    $b->shouldReceive('where')->once()->with('is_active', true)->andReturnSelf();

    $c->apply($b, 'is_true', null);
});

it('BooleanConstraint: is_false applies where(field, false)', function (): void {
    $c = BooleanConstraint::make('is_active');
    $b = boolBuilder();
    $b->shouldReceive('where')->once()->with('is_active', false)->andReturnSelf();

    $c->apply($b, 'is_false', null);
});

it('BooleanConstraint: respects orWhere $method param', function (): void {
    $c = BooleanConstraint::make('is_active');
    $b = boolBuilder();
    $b->shouldReceive('orWhere')->once()->with('is_active', true)->andReturnSelf();

    $c->apply($b, 'is_true', null, 'orWhere');
});
