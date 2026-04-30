<?php

declare(strict_types=1);

use Arqel\Table\Filters\Constraints\DateConstraint;
use Illuminate\Database\Eloquent\Builder;

function dateBuilder(): Builder
{
    return Mockery::mock(Builder::class);
}

it('DateConstraint: default operators and type', function (): void {
    $c = DateConstraint::make('created_at');

    expect($c->getType())->toBe('date')
        ->and($c->getDefaultOperators())->toBe(['=', 'before', 'after', 'between']);
});

it('DateConstraint: before uses < comparator', function (): void {
    $c = DateConstraint::make('created_at');
    $b = dateBuilder();
    $b->shouldReceive('where')->once()
        ->with('created_at', '<', Mockery::pattern('/^2026-01-01/'))
        ->andReturnSelf();

    $c->apply($b, 'before', '2026-01-01');
});

it('DateConstraint: between uses whereBetween with [from, to]', function (): void {
    $c = DateConstraint::make('created_at');
    $b = dateBuilder();
    $b->shouldReceive('whereBetween')->once()
        ->with('created_at', Mockery::on(function ($range) {
            return is_array($range)
                && count($range) === 2
                && str_starts_with($range[0], '2026-01-01')
                && str_starts_with($range[1], '2026-12-31');
        }))
        ->andReturnSelf();

    $c->apply($b, 'between', ['2026-01-01', '2026-12-31']);
});

it('DateConstraint: invalid date throws InvalidArgumentException', function (): void {
    $c = DateConstraint::make('created_at');
    $b = dateBuilder();

    expect(fn () => $c->apply($b, '=', 'not-a-date-at-all'))
        ->toThrow(InvalidArgumentException::class);
});

it('DateConstraint: between with non-array throws', function (): void {
    $c = DateConstraint::make('created_at');
    $b = dateBuilder();

    expect(fn () => $c->apply($b, 'between', '2026-01-01'))
        ->toThrow(InvalidArgumentException::class);
});
