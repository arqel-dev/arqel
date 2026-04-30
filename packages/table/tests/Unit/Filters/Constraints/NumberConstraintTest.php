<?php

declare(strict_types=1);

use Arqel\Table\Filters\Constraints\NumberConstraint;
use Illuminate\Database\Eloquent\Builder;

function numberBuilder(): Builder
{
    return Mockery::mock(Builder::class);
}

it('NumberConstraint: default operators and type', function (): void {
    $c = NumberConstraint::make('age');

    expect($c->getType())->toBe('number')
        ->and($c->getDefaultOperators())->toBe(['=', '!=', '>', '<', '>=', '<=', 'between']);
});

it('NumberConstraint: applies = comparison with int cast', function (): void {
    $c = NumberConstraint::make('age');
    $b = numberBuilder();
    $b->shouldReceive('where')->once()->with('age', '>', 18)->andReturnSelf();

    $c->apply($b, '>', '18');
});

it('NumberConstraint: between uses whereBetween with [min, max]', function (): void {
    $c = NumberConstraint::make('age');
    $b = numberBuilder();
    $b->shouldReceive('whereBetween')->once()->with('age', [18, 65])->andReturnSelf();

    $c->apply($b, 'between', [18, 65]);
});

it('NumberConstraint: between with orWhere uses orWhereBetween', function (): void {
    $c = NumberConstraint::make('age');
    $b = numberBuilder();
    $b->shouldReceive('orWhereBetween')->once()->with('age', [10, 20])->andReturnSelf();

    $c->apply($b, 'between', [10, 20], 'orWhere');
});

it('NumberConstraint: throws when value is not numeric', function (): void {
    $c = NumberConstraint::make('age');
    $b = numberBuilder();

    expect(fn () => $c->apply($b, '=', 'not-a-number'))
        ->toThrow(InvalidArgumentException::class);
});

it('NumberConstraint: between throws when value is not a 2-element array', function (): void {
    $c = NumberConstraint::make('age');
    $b = numberBuilder();

    expect(fn () => $c->apply($b, 'between', 'oops'))
        ->toThrow(InvalidArgumentException::class);
});
