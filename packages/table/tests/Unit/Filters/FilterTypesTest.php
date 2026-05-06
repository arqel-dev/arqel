<?php

declare(strict_types=1);

use Arqel\Table\Filters\DateRangeFilter;
use Arqel\Table\Filters\MultiSelectFilter;
use Arqel\Table\Filters\ScopeFilter;
use Arqel\Table\Filters\SelectFilter;
use Arqel\Table\Filters\TernaryFilter;
use Arqel\Table\Filters\TextFilter;
use Illuminate\Database\Eloquent\Builder;

function fakeBuilder(): Builder
{
    return Mockery::mock(Builder::class);
}

it('SelectFilter: type, label auto-derive, options, and apply', function (): void {
    $filter = SelectFilter::make('role_id')
        ->options([1 => 'Admin', 2 => 'User']);

    $builder = fakeBuilder();
    $builder->shouldReceive('where')->once()->with('role_id', 2)->andReturnSelf();

    $result = $filter->applyToQuery($builder, 2);

    expect($filter->getType())->toBe('select')
        ->and($filter->getLabel())->toBe('Role Id')
        ->and($filter->resolveOptions())->toBe([1 => 'Admin', 2 => 'User'])
        ->and($result)->toBe($builder);
});

it('SelectFilter: skips applying when value is null or empty string', function (): void {
    $filter = SelectFilter::make('role_id');
    $builder = fakeBuilder();
    $builder->shouldNotReceive('where');

    expect($filter->applyToQuery($builder, null))->toBe($builder)
        ->and($filter->applyToQuery($builder, ''))->toBe($builder);
});

it('SelectFilter: closure-based options resolved at serialise time', function (): void {
    $filter = SelectFilter::make('cat')->options(fn () => [10 => 'A', 20 => 'B']);

    expect($filter->resolveOptions())->toBe([10 => 'A', 20 => 'B']);
});

it('SelectFilter: getTypeSpecificProps normalizes options to {value, label} array for the React side', function (): void {
    $filter = SelectFilter::make('status')
        ->options(['draft' => 'Draft', 'published' => 'Published']);

    expect($filter->getTypeSpecificProps()['options'])->toBe([
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'published', 'label' => 'Published'],
    ]);
});

it('SelectFilter: relationship metadata recorded for controller-side resolution', function (): void {
    $filter = SelectFilter::make('cat')->optionsRelationship('category', 'name');

    expect($filter->getOptionsRelation())->toBe('category')
        ->and($filter->getOptionsRelationDisplay())->toBe('name')
        ->and($filter->resolveOptions())->toBe([]);
});

it('MultiSelectFilter: applies whereIn for arrays and skips empty/non-array', function (): void {
    $filter = MultiSelectFilter::make('tags');

    $builder = fakeBuilder();
    $builder->shouldReceive('whereIn')->once()->with('tags', [1, 2])->andReturnSelf();
    expect($filter->applyToQuery($builder, [1, 2]))->toBe($builder);

    $idle = fakeBuilder();
    $idle->shouldNotReceive('whereIn');
    expect($filter->applyToQuery($idle, []))->toBe($idle)
        ->and($filter->applyToQuery($idle, 'not-array'))->toBe($idle);
});

it('DateRangeFilter: applies whereBetween for full range', function (): void {
    $filter = DateRangeFilter::make('created_at');

    $builder = fakeBuilder();
    $builder->shouldReceive('whereBetween')
        ->once()
        ->with('created_at', ['2026-01-01', '2026-12-31'])
        ->andReturnSelf();

    expect($filter->applyToQuery($builder, ['from' => '2026-01-01', 'to' => '2026-12-31']))
        ->toBe($builder);
});

it('DateRangeFilter: applies open-ended ranges', function (): void {
    $fromOnly = DateRangeFilter::make('created_at');
    $b1 = fakeBuilder();
    $b1->shouldReceive('where')->once()->with('created_at', '>=', '2026-01-01')->andReturnSelf();
    expect($fromOnly->applyToQuery($b1, ['from' => '2026-01-01', 'to' => '']))->toBe($b1);

    $toOnly = DateRangeFilter::make('created_at');
    $b2 = fakeBuilder();
    $b2->shouldReceive('where')->once()->with('created_at', '<=', '2026-12-31')->andReturnSelf();
    expect($toOnly->applyToQuery($b2, ['from' => '', 'to' => '2026-12-31']))->toBe($b2);
});

it('DateRangeFilter: skips when value is not an array', function (): void {
    $filter = DateRangeFilter::make('created_at');
    $b = fakeBuilder();
    $b->shouldNotReceive('whereBetween');
    $b->shouldNotReceive('where');

    expect($filter->applyToQuery($b, 'oops'))->toBe($b);
});

it('DateRangeFilter: serialises minDate/maxDate via Closure', function (): void {
    $filter = DateRangeFilter::make('created_at')
        ->minDate(fn () => '2020-01-01')
        ->maxDate('2030-12-31');

    $props = $filter->getTypeSpecificProps();

    expect($props['minDate'])->toBe('2020-01-01')
        ->and($props['maxDate'])->toBe('2030-12-31');
});

it('TextFilter: wraps value in % LIKE %', function (): void {
    $filter = TextFilter::make('name');

    $b = fakeBuilder();
    $b->shouldReceive('where')->once()->with('name', 'LIKE', '%alice%')->andReturnSelf();

    expect($filter->applyToQuery($b, 'alice'))->toBe($b);
});

it('TextFilter: skips empty strings', function (): void {
    $filter = TextFilter::make('name');
    $b = fakeBuilder();
    $b->shouldNotReceive('where');
    expect($filter->applyToQuery($b, ''))->toBe($b);
});

it('TernaryFilter: coerces strings into booleans', function (): void {
    $filter = TernaryFilter::make('is_active');

    $btrue = fakeBuilder();
    $btrue->shouldReceive('where')->once()->with('is_active', true)->andReturnSelf();
    $filter->applyToQuery($btrue, 'true');

    $bfalse = fakeBuilder();
    $bfalse->shouldReceive('where')->once()->with('is_active', false)->andReturnSelf();
    $filter->applyToQuery($bfalse, '0');

    $ball = fakeBuilder();
    $ball->shouldNotReceive('where');
    expect($filter->applyToQuery($ball, 'all'))->toBe($ball);
});

it('ScopeFilter: invokes the scope when value is truthy', function (): void {
    $filter = ScopeFilter::make('published')->scope('published');

    $b = Mockery::mock(Builder::class);
    $b->shouldReceive('published')->once()->andReturnSelf();

    expect($filter->applyToQuery($b, true))->toBe($b);
});

it('ScopeFilter: leaves the query alone when value is falsy', function (): void {
    $filter = ScopeFilter::make('published');
    $b = fakeBuilder();
    $b->shouldNotReceive('published');

    expect($filter->applyToQuery($b, false))->toBe($b);
});

it('Filter.apply Closure overrides default behaviour', function (): void {
    $filter = SelectFilter::make('role_id')
        ->apply(fn (Builder $q, $v) => $q->where('custom', $v));

    $b = fakeBuilder();
    $b->shouldReceive('where')->once()->with('custom', 'admin')->andReturnSelf();

    expect($filter->applyToQuery($b, 'admin'))->toBe($b);
});

it('Filter.toArray serialises label, default, persist, and props', function (): void {
    $filter = SelectFilter::make('role_id')
        ->label('Função')
        ->default(1)
        ->persist(false)
        ->options([1 => 'Admin']);

    $payload = $filter->toArray();

    expect($payload['type'])->toBe('select')
        ->and($payload['name'])->toBe('role_id')
        ->and($payload['label'])->toBe('Função')
        ->and($payload['default'])->toBe(1)
        ->and($payload['persist'])->toBeFalse()
        ->and($payload['props']['options'])->toBe([
            ['value' => 1, 'label' => 'Admin'],
        ]);
});
