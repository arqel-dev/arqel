<?php

declare(strict_types=1);

use Arqel\Table\Filters\Constraints\BooleanConstraint;
use Arqel\Table\Filters\Constraints\NumberConstraint;
use Arqel\Table\Filters\Constraints\TextConstraint;
use Arqel\Table\Filters\QueryBuilderFilter;
use Illuminate\Database\Eloquent\Builder;

function qbBuilder(): Builder
{
    return Mockery::mock(Builder::class);
}

it('QueryBuilderFilter: non-array value is a no-op', function (): void {
    $filter = QueryBuilderFilter::make('advanced')
        ->constraints([TextConstraint::make('name')]);

    $b = qbBuilder();
    $b->shouldNotReceive('where');

    expect($filter->applyToQuery($b, 'oops'))->toBe($b);
});

it('QueryBuilderFilter: empty conditions is a no-op', function (): void {
    $filter = QueryBuilderFilter::make('advanced')
        ->constraints([TextConstraint::make('name')]);

    $b = qbBuilder();
    $b->shouldNotReceive('where');

    expect($filter->applyToQuery($b, ['conditions' => []]))->toBe($b);
});

it('QueryBuilderFilter: flat AND conditions invoke where on each leaf', function (): void {
    $filter = QueryBuilderFilter::make('advanced')->constraints([
        TextConstraint::make('name'),
        NumberConstraint::make('age'),
    ]);

    $inner = qbBuilder();
    $inner->shouldReceive('where')->once()->with('name', 'LIKE', '%alice%')->andReturnSelf();
    $inner->shouldReceive('where')->once()->with('age', '>', 18)->andReturnSelf();

    $outer = qbBuilder();
    $outer->shouldReceive('where')->once()->with(Mockery::on(function (Closure $cb) use ($inner): bool {
        $cb($inner);

        return true;
    }))->andReturnSelf();

    $filter->applyToQuery($outer, [
        'operator' => 'AND',
        'conditions' => [
            ['field' => 'name', 'operator' => 'contains', 'value' => 'alice'],
            ['field' => 'age', 'operator' => '>', 'value' => 18],
        ],
    ]);
});

it('QueryBuilderFilter: flat OR conditions invoke orWhere on each leaf', function (): void {
    $filter = QueryBuilderFilter::make('advanced')->constraints([
        TextConstraint::make('name'),
        BooleanConstraint::make('is_active'),
    ]);

    $inner = qbBuilder();
    $inner->shouldReceive('orWhere')->once()->with('name', '=', 'alice')->andReturnSelf();
    $inner->shouldReceive('orWhere')->once()->with('is_active', true)->andReturnSelf();

    $outer = qbBuilder();
    $outer->shouldReceive('where')->once()->with(Mockery::on(function (Closure $cb) use ($inner): bool {
        $cb($inner);

        return true;
    }))->andReturnSelf();

    $filter->applyToQuery($outer, [
        'operator' => 'OR',
        'conditions' => [
            ['field' => 'name', 'operator' => 'equals', 'value' => 'alice'],
            ['field' => 'is_active', 'operator' => 'is_true', 'value' => null],
        ],
    ]);
});

it('QueryBuilderFilter: nested groups recurse with own operator', function (): void {
    $filter = QueryBuilderFilter::make('advanced')->constraints([
        TextConstraint::make('name'),
        NumberConstraint::make('age'),
    ]);

    $nested = qbBuilder();
    $nested->shouldReceive('orWhere')->once()->with('age', '>', 18)->andReturnSelf();
    $nested->shouldReceive('orWhere')->once()->with('age', '<', 65)->andReturnSelf();

    $inner = qbBuilder();
    $inner->shouldReceive('where')->once()->with('name', 'LIKE', '%alice%')->andReturnSelf();
    $inner->shouldReceive('where')->once()->with(Mockery::on(function (Closure $cb) use ($nested): bool {
        $cb($nested);

        return true;
    }))->andReturnSelf();

    $outer = qbBuilder();
    $outer->shouldReceive('where')->once()->with(Mockery::on(function (Closure $cb) use ($inner): bool {
        $cb($inner);

        return true;
    }))->andReturnSelf();

    $filter->applyToQuery($outer, [
        'operator' => 'AND',
        'conditions' => [
            ['field' => 'name', 'operator' => 'contains', 'value' => 'alice'],
            [
                'group' => true,
                'operator' => 'OR',
                'conditions' => [
                    ['field' => 'age', 'operator' => '>', 'value' => 18],
                    ['field' => 'age', 'operator' => '<', 'value' => 65],
                ],
            ],
        ],
    ]);
});

it('QueryBuilderFilter: unknown field is silently dropped (security)', function (): void {
    $filter = QueryBuilderFilter::make('advanced')->constraints([
        TextConstraint::make('name'),
    ]);

    $inner = qbBuilder();
    // Only the known 'name' field should hit the inner builder.
    $inner->shouldReceive('where')->once()->with('name', '=', 'alice')->andReturnSelf();

    $outer = qbBuilder();
    $outer->shouldReceive('where')->once()->with(Mockery::on(function (Closure $cb) use ($inner): bool {
        $cb($inner);

        return true;
    }))->andReturnSelf();

    $filter->applyToQuery($outer, [
        'operator' => 'AND',
        'conditions' => [
            ['field' => 'name', 'operator' => 'equals', 'value' => 'alice'],
            // Attempted SQL injection / arbitrary field — must be dropped.
            ['field' => 'password; DROP TABLE users--', 'operator' => '=', 'value' => 'x'],
            ['field' => 'unknown_field', 'operator' => '=', 'value' => 'whatever'],
        ],
    ]);
});

it('QueryBuilderFilter: unknown operator on known field is silently dropped', function (): void {
    $filter = QueryBuilderFilter::make('advanced')->constraints([
        TextConstraint::make('name'),
    ]);

    $inner = qbBuilder();
    $inner->shouldNotReceive('where');

    $outer = qbBuilder();
    $outer->shouldReceive('where')->once()->with(Mockery::on(function (Closure $cb) use ($inner): bool {
        $cb($inner);

        return true;
    }))->andReturnSelf();

    $filter->applyToQuery($outer, [
        'operator' => 'AND',
        'conditions' => [
            ['field' => 'name', 'operator' => 'between', 'value' => 'alice'],
        ],
    ]);
});

it('QueryBuilderFilter: constraints() filters non-Constraint entries', function (): void {
    $filter = QueryBuilderFilter::make('advanced')->constraints([
        TextConstraint::make('name'),
        'not-a-constraint',
        42,
        new stdClass,
        NumberConstraint::make('age'),
    ]);

    expect($filter->getConstraints())->toHaveCount(2)
        ->and($filter->getConstraints()[0])->toBeInstanceOf(TextConstraint::class)
        ->and($filter->getConstraints()[1])->toBeInstanceOf(NumberConstraint::class);
});

it('QueryBuilderFilter: toArray exposes type + constraints payload', function (): void {
    $filter = QueryBuilderFilter::make('advanced')->constraints([
        TextConstraint::make('name'),
        NumberConstraint::make('age')->operators(['=', '>']),
    ]);

    $payload = $filter->toArray();

    expect($payload['type'])->toBe('queryBuilder')
        ->and($payload['name'])->toBe('advanced')
        ->and($payload['props']['constraints'])->toHaveCount(2)
        ->and($payload['props']['constraints'][0]['field'])->toBe('name')
        ->and($payload['props']['constraints'][0]['type'])->toBe('text')
        ->and($payload['props']['constraints'][1]['field'])->toBe('age')
        ->and($payload['props']['constraints'][1]['operators'])->toBe(['=', '>']);
});
