<?php

declare(strict_types=1);

use Arqel\Table\Filters\Constraints\TextConstraint;
use Illuminate\Database\Eloquent\Builder;

function textBuilder(): Builder
{
    return Mockery::mock(Builder::class);
}

it('TextConstraint: default operators and type', function (): void {
    $c = TextConstraint::make('name');

    expect($c->getType())->toBe('text')
        ->and($c->getDefaultOperators())->toBe(['equals', 'not_equals', 'contains', 'starts_with', 'ends_with']);
});

it('TextConstraint: label fallback humanises field via Str::headline', function (): void {
    $c = TextConstraint::make('first_name');

    expect($c->getLabel())->toBe('First Name');
});

it('TextConstraint: explicit label() overrides fallback', function (): void {
    $c = TextConstraint::make('email_address')->label('Inbox');

    expect($c->getLabel())->toBe('Inbox');
});

it('TextConstraint: contains produces LIKE %value%', function (): void {
    $c = TextConstraint::make('name');
    $b = textBuilder();
    $b->shouldReceive('where')->once()->with('name', 'LIKE', '%alice%')->andReturnSelf();

    $c->apply($b, 'contains', 'alice');
});

it('TextConstraint: not_equals uses != operator', function (): void {
    $c = TextConstraint::make('name');
    $b = textBuilder();
    $b->shouldReceive('where')->once()->with('name', '!=', 'alice')->andReturnSelf();

    $c->apply($b, 'not_equals', 'alice');
});

it('TextConstraint: starts_with and ends_with build correct LIKE patterns', function (): void {
    $c = TextConstraint::make('name');

    $bs = textBuilder();
    $bs->shouldReceive('where')->once()->with('name', 'LIKE', 'al%')->andReturnSelf();
    $c->apply($bs, 'starts_with', 'al');

    $be = textBuilder();
    $be->shouldReceive('where')->once()->with('name', 'LIKE', '%ce')->andReturnSelf();
    $c->apply($be, 'ends_with', 'ce');
});

it('TextConstraint: respects $method param for orWhere variant', function (): void {
    $c = TextConstraint::make('name');
    $b = textBuilder();
    $b->shouldReceive('orWhere')->once()->with('name', 'LIKE', '%bob%')->andReturnSelf();

    $c->apply($b, 'contains', 'bob', 'orWhere');
});

it('TextConstraint: toArray serialises field/label/type/operators', function (): void {
    $c = TextConstraint::make('name')->operators(['equals', 'contains']);

    expect($c->toArray())->toBe([
        'field' => 'name',
        'label' => 'Name',
        'type' => 'text',
        'operators' => ['equals', 'contains'],
    ]);
});
