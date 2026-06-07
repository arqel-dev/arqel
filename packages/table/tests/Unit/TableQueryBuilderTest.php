<?php

declare(strict_types=1);

use Arqel\Table\Columns\RelationshipColumn;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Filters\SelectFilter;
use Arqel\Table\Table;
use Arqel\Table\TableQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

function tqbBuilder(): Builder
{
    $b = Mockery::mock(Builder::class);
    $b->shouldReceive('paginate')
        ->andReturnUsing(function () {
            $p = Mockery::mock(Illuminate\Contracts\Pagination\LengthAwarePaginator::class);
            $p->shouldReceive('withQueryString')->andReturnSelf();

            return $p;
        });

    return $b;
}

it('searches with OR LIKE across searchable columns', function (): void {
    $table = (new Table)
        ->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('email')->searchable(),
            TextColumn::make('status'),
        ]);

    $builder = tqbBuilder();
    $builder->shouldReceive('where')
        ->once()
        ->with(Mockery::on(function (Closure $cb): bool {
            $inner = Mockery::mock(Builder::class);
            $inner->shouldReceive('orWhere')->twice()->andReturnSelf();
            $cb($inner);

            return true;
        }))
        ->andReturnSelf();

    (new TableQueryBuilder($table, $builder, Request::create('/', 'GET', ['search' => 'lic'])))->build();
});

it('skips search when table is not searchable', function (): void {
    $table = (new Table)
        ->columns([TextColumn::make('name')->searchable()])
        ->searchable(false);

    $builder = tqbBuilder();
    $builder->shouldNotReceive('where');

    (new TableQueryBuilder($table, $builder, Request::create('/', 'GET', ['search' => 'foo'])))->build();
});

it('skips search when no column is searchable', function (): void {
    $table = (new Table)->columns([TextColumn::make('name')]);

    $builder = tqbBuilder();
    $builder->shouldNotReceive('where');

    (new TableQueryBuilder($table, $builder, Request::create('/', 'GET', ['search' => 'foo'])))->build();
});

it('applies registered filters with the request value', function (): void {
    $filter = Mockery::mock(SelectFilter::class.'[applyToQuery]', ['status']);
    $filter->shouldReceive('applyToQuery')
        ->once()
        ->with(Mockery::any(), 'published')
        ->andReturnUsing(fn ($q) => $q);

    $table = (new Table)
        ->columns([TextColumn::make('status')])
        ->filters([$filter]);

    $builder = tqbBuilder();
    (new TableQueryBuilder($table, $builder, Request::create('/', 'GET', ['filter' => ['status' => 'published']])))->build();
});

it('passes filter default to applyToQuery when request omits the key', function (): void {
    $filter = Mockery::mock(SelectFilter::class.'[applyToQuery]', ['status']);
    $filter->shouldReceive('applyToQuery')
        ->once()
        ->with(Mockery::any(), 'draft')
        ->andReturnUsing(fn ($q) => $q);

    /** @var SelectFilter $filter */
    $filter->default('draft');

    $table = (new Table)
        ->columns([TextColumn::make('status')])
        ->filters([$filter]);

    $builder = tqbBuilder();
    (new TableQueryBuilder($table, $builder, Request::create('/')))->build();
});

it('skips non-Filter entries in the filter list', function (): void {
    $table = (new Table)
        ->columns([TextColumn::make('status')])
        ->filters(['not-a-filter', null]);

    $builder = tqbBuilder();
    // No expectations — just must not throw.

    expect((new TableQueryBuilder($table, $builder, Request::create('/')))->build())
        ->toBeInstanceOf(Illuminate\Contracts\Pagination\LengthAwarePaginator::class);
});

it('orders by sortable columns when the request asks for it', function (): void {
    $table = (new Table)
        ->columns([TextColumn::make('name')->sortable()]);

    $builder = tqbBuilder();
    $builder->shouldReceive('orderBy')->once()->with('name', 'asc')->andReturnSelf();

    (new TableQueryBuilder($table, $builder, Request::create('/', 'GET', ['sort' => 'name', 'direction' => 'asc'])))->build();
});

it('rejects sort against non-sortable columns', function (): void {
    $table = (new Table)
        ->columns([TextColumn::make('name')]);

    $builder = tqbBuilder();
    $builder->shouldNotReceive('orderBy');

    (new TableQueryBuilder($table, $builder, Request::create('/', 'GET', ['sort' => 'name'])))->build();
});

it('falls back to default direction when request direction is invalid', function (): void {
    $table = (new Table)
        ->columns([TextColumn::make('name')->sortable()]);

    $builder = tqbBuilder();
    $builder->shouldReceive('orderBy')->once()->with('name', 'desc')->andReturnSelf();

    (new TableQueryBuilder($table, $builder, Request::create('/', 'GET', ['sort' => 'name', 'direction' => 'sideways'])))->build();
});

it('uses the table default sort when nothing is requested', function (): void {
    $table = (new Table)
        ->columns([TextColumn::make('name')->sortable()])
        ->defaultSort('name', 'desc');

    $builder = tqbBuilder();
    $builder->shouldReceive('orderBy')->once()->with('name', 'desc')->andReturnSelf();

    (new TableQueryBuilder($table, $builder, Request::create('/')))->build();
});

it('does not order by the relation name for a sortable relationship column', function (): void {
    // Relationship columns are keyed by the relation name (e.g. "author"),
    // which is not a real DB column. Ordering by it would raise an
    // "Unknown column" SQL error. Relationship sort is deferred to
    // TABLE-005, so the column must be excluded from the sort whitelist.
    $table = (new Table)
        ->columns([
            RelationshipColumn::make('author')->display('name')->sortable(),
            TextColumn::make('title'),
        ]);

    $builder = tqbBuilder();
    $builder->shouldReceive('with')->andReturnSelf(); // eager loading is unrelated to the defect
    $builder->shouldNotReceive('orderBy');

    (new TableQueryBuilder($table, $builder, Request::create('/', 'GET', ['sort' => 'author', 'direction' => 'asc'])))->build();
});

it('does not search against the relation name for a searchable relationship column', function (): void {
    // Same defect as sort: "author" is a relation accessor, not a column.
    // A searchable relationship column must not reach the WHERE whitelist.
    $table = (new Table)
        ->columns([
            RelationshipColumn::make('author')->display('name')->searchable(),
        ]);

    $builder = tqbBuilder();
    $builder->shouldReceive('with')->andReturnSelf(); // eager loading is unrelated to the defect
    $builder->shouldNotReceive('where');

    (new TableQueryBuilder($table, $builder, Request::create('/', 'GET', ['search' => 'jane'])))->build();
});

it('eager loads relationship columns via with()', function (): void {
    $table = (new Table)
        ->columns([
            RelationshipColumn::make('author'),
            RelationshipColumn::make('team'),
            TextColumn::make('name'),
        ]);

    $builder = tqbBuilder();
    $builder->shouldReceive('with')->once()->with(['author', 'team'])->andReturnSelf();

    (new TableQueryBuilder($table, $builder, Request::create('/')))->build();
});

it('skips eager loading when there are no relationship columns', function (): void {
    $table = (new Table)->columns([TextColumn::make('name')]);

    $builder = tqbBuilder();
    $builder->shouldNotReceive('with');

    (new TableQueryBuilder($table, $builder, Request::create('/')))->build();
});

it('uses the default per-page when nothing is requested', function (): void {
    $table = (new Table)
        ->columns([TextColumn::make('name')])
        ->perPage(50);

    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('paginate')
        ->once()
        ->with(50)
        ->andReturnUsing(function () {
            $p = Mockery::mock(Illuminate\Contracts\Pagination\LengthAwarePaginator::class);
            $p->shouldReceive('withQueryString')->andReturnSelf();

            return $p;
        });

    (new TableQueryBuilder($table, $builder, Request::create('/')))->build();
});

it('honours an allowed per_page request override', function (): void {
    $table = (new Table)
        ->columns([TextColumn::make('name')])
        ->perPage(10, [10, 25, 50]);

    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('paginate')
        ->once()
        ->with(25)
        ->andReturnUsing(function () {
            $p = Mockery::mock(Illuminate\Contracts\Pagination\LengthAwarePaginator::class);
            $p->shouldReceive('withQueryString')->andReturnSelf();

            return $p;
        });

    (new TableQueryBuilder($table, $builder, Request::create('/', 'GET', ['per_page' => '25'])))->build();
});

it('rejects per_page values outside the configured options', function (): void {
    $table = (new Table)
        ->columns([TextColumn::make('name')])
        ->perPage(10, [10, 25]);

    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('paginate')
        ->once()
        ->with(10) // default, not 999
        ->andReturnUsing(function () {
            $p = Mockery::mock(Illuminate\Contracts\Pagination\LengthAwarePaginator::class);
            $p->shouldReceive('withQueryString')->andReturnSelf();

            return $p;
        });

    (new TableQueryBuilder($table, $builder, Request::create('/', 'GET', ['per_page' => '999'])))->build();
});
