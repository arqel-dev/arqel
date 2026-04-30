<?php

declare(strict_types=1);

use Arqel\Table\Columns\SelectColumn;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Columns\TextInputColumn;
use Arqel\Table\Columns\ToggleColumn;
use Arqel\Table\Filters\Constraints\TextConstraint;
use Arqel\Table\Filters\QueryBuilderFilter;
use Arqel\Table\Filters\SelectFilter;
use Arqel\Table\Summaries\Summary;
use Arqel\Table\Table;
use Illuminate\Support\Collection;

it('serialises ALL TABLE-V2 features in toArray()', function (): void {
    $table = (new Table)
        ->columns([TextColumn::make('title')])
        ->groupBy('status')
        ->groupSummaries([
            Summary::count(),
            Summary::sum('amount'),
        ])
        ->reorderable('position')
        ->mobileMode(Table::MOBILE_MODE_STACKED);

    $payload = $table->toArray();

    expect($payload)
        ->toHaveKeys(['groupBy', 'summaries', 'reorderable'])
        ->and($payload['groupBy'])->toBe('status')
        ->and($payload['summaries'])->toHaveCount(2)
        ->and($payload['summaries'][0])->toMatchArray(['type' => 'count'])
        ->and($payload['summaries'][1])->toMatchArray(['type' => 'sum', 'field' => 'amount'])
        ->and($payload['reorderable'])->toBe('position')
        ->and($payload['config'])->toHaveKey('mobileMode')
        ->and($payload['config']['mobileMode'])->toBe(Table::MOBILE_MODE_STACKED);
});

it('combines togglable columns with grouping', function (): void {
    $table = (new Table)
        ->columns([
            TextColumn::make('title'),
            TextColumn::make('notes')->togglable()->hiddenByDefault(),
        ])
        ->groupBy('status');

    $payload = $table->toArray();

    expect($payload['groupBy'])->toBe('status')
        ->and($payload['columns'])->toHaveCount(2);

    /** @var array<int, TextColumn> $cols */
    $cols = $payload['columns'];

    expect($cols[1]->isTogglable())->toBeTrue()
        ->and($cols[1]->isHiddenByDefault())->toBeTrue()
        ->and($cols[1]->toArray())
        ->toMatchArray([
            'togglable' => true,
            'hiddenByDefault' => true,
        ]);
});

it('QueryBuilderFilter co-exists with regular filters', function (): void {
    $table = (new Table)
        ->columns([TextColumn::make('title')])
        ->filters([
            QueryBuilderFilter::make('advanced')
                ->constraints([TextConstraint::make('title')]),
            SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
        ]);

    $payload = $table->toArray();
    $filters = $payload['filters'];

    expect($filters)->toHaveCount(2)
        ->and($filters[0])->toBeInstanceOf(QueryBuilderFilter::class)
        ->and($filters[1])->toBeInstanceOf(SelectFilter::class)
        ->and($filters[0]->toArray()['type'])->toBe('queryBuilder')
        ->and($filters[1]->toArray()['type'])->toBe('select');
});

it('inline editing columns render in the columns array', function (): void {
    $table = (new Table)
        ->columns([
            TextInputColumn::make('title')->rules(['required', 'max:200']),
            SelectColumn::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
            ToggleColumn::make('is_featured'),
        ]);

    $payload = $table->toArray();
    $cols = $payload['columns'];

    expect($cols)->toHaveCount(3);

    $serialised = array_map(static fn (object $col) => $col->toArray(), $cols);

    expect($serialised[0]['type'])->toBe('textInput')
        ->and($serialised[0])->toHaveKeys(['editable', 'debounce', 'rules'])
        ->and($serialised[0]['rules'])->toBe(['required', 'max:200'])
        ->and($serialised[1]['type'])->toBe('select')
        ->and($serialised[2]['type'])->toBe('toggle');
});

it('grouping with reorderable + summaries on the same Table', function (): void {
    $table = (new Table)
        ->columns([TextColumn::make('title')])
        ->groupBy('status')
        ->groupSummaries([
            Summary::count(),
            Summary::sum('amount'),
        ])
        ->reorderable('position');

    $records = new Collection([
        (object) ['status' => 'draft', 'amount' => 10],
        (object) ['status' => 'draft', 'amount' => 20],
        (object) ['status' => 'published', 'amount' => 100],
    ]);

    $groups = $table->buildGroups($records);

    expect($table->isReorderable())->toBeTrue()
        ->and($table->getReorderColumn())->toBe('position')
        ->and($groups)->toHaveCount(2);

    $byKey = collect($groups)->keyBy('key');

    expect($byKey['draft']['records']->count())->toBe(2)
        ->and($byKey['published']['records']->count())->toBe(1)
        ->and($byKey['draft']['summaries'])->toHaveCount(2)
        ->and($byKey['draft']['summaries'][0])->toMatchArray(['type' => 'count', 'value' => 2])
        ->and($byKey['draft']['summaries'][1])->toMatchArray(['type' => 'sum', 'field' => 'amount', 'value' => 30])
        ->and($byKey['published']['summaries'][1])->toMatchArray(['type' => 'sum', 'value' => 100]);
});

it('grouping + reorderable + mobileMode does not suppress the rest of the config', function (): void {
    $table = (new Table)
        ->columns([TextColumn::make('title')])
        ->groupBy('status')
        ->reorderable('position')
        ->mobileMode(Table::MOBILE_MODE_SCROLL)
        ->searchable()
        ->selectable()
        ->striped()
        ->compact()
        ->perPage(50);

    $payload = $table->toArray();

    expect($payload['config'])->toMatchArray([
        'searchable' => true,
        'selectable' => true,
        'striped' => true,
        'compact' => true,
        'defaultPerPage' => 50,
        'mobileMode' => Table::MOBILE_MODE_SCROLL,
    ])
        ->and($payload['groupBy'])->toBe('status')
        ->and($payload['reorderable'])->toBe('position');
});

it('mobileMode scroll preserves all columns including hiddenOnMobile flag', function (): void {
    $table = (new Table)
        ->columns([
            TextColumn::make('title'),
            TextColumn::make('internal_id')->hiddenOnMobile(),
            TextColumn::make('notes')->hiddenOnMobile()->togglable(),
        ])
        ->mobileMode(Table::MOBILE_MODE_SCROLL);

    $payload = $table->toArray();

    expect($payload['config']['mobileMode'])->toBe(Table::MOBILE_MODE_SCROLL)
        ->and($payload['columns'])->toHaveCount(3);

    $serialised = array_map(static fn (object $col) => $col->toArray(), $payload['columns']);

    expect($serialised[0]['hiddenOnMobile'])->toBeFalse()
        ->and($serialised[1]['hiddenOnMobile'])->toBeTrue()
        ->and($serialised[2]['hiddenOnMobile'])->toBeTrue()
        ->and($serialised[2]['togglable'])->toBeTrue();
});
