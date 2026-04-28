<?php

declare(strict_types=1);

use Arqel\Table\Column;
use Arqel\Table\Columns\BadgeColumn;
use Arqel\Table\Columns\BooleanColumn;
use Arqel\Table\Columns\ComputedColumn;
use Arqel\Table\Columns\DateColumn;
use Arqel\Table\Columns\IconColumn;
use Arqel\Table\Columns\ImageColumn;
use Arqel\Table\Columns\NumberColumn;
use Arqel\Table\Columns\RelationshipColumn;
use Arqel\Table\Columns\TextColumn;

it('TextColumn: type, label auto-derive, fluent toggles, and serialisation', function (): void {
    $column = TextColumn::make('full_name')
        ->sortable()
        ->searchable()
        ->copyable()
        ->limit(50)
        ->wrap()
        ->fontFamily('mono');

    $payload = $column->toArray();

    expect($column->getType())->toBe('text')
        ->and($column->getLabel())->toBe('Full Name')
        ->and($column->isSortable())->toBeTrue()
        ->and($column->isSearchable())->toBeTrue()
        ->and($column->isCopyable())->toBeTrue()
        ->and($payload['props'])->toBe([
            'limit' => 50,
            'wrap' => true,
            'fontFamily' => 'mono',
        ]);
});

it('honours alignment shortcuts and width', function (): void {
    $center = TextColumn::make('x')->alignCenter()->width('120px');
    $start = TextColumn::make('y')->alignStart();
    $end = TextColumn::make('z')->alignEnd();

    expect($center->getAlignment())->toBe(Column::ALIGN_CENTER)
        ->and($center->getWidth())->toBe('120px')
        ->and($start->getAlignment())->toBe('start')
        ->and($end->getAlignment())->toBe('end');
});

it('BadgeColumn: colors and icons maps appear in props when set', function (): void {
    $column = BadgeColumn::make('status')
        ->colors(['draft' => 'gray', 'published' => 'emerald'])
        ->icons(['draft' => 'pencil']);

    $props = $column->getTypeSpecificProps();

    expect($column->getType())->toBe('badge')
        ->and($props['colors'])->toBe(['draft' => 'gray', 'published' => 'emerald'])
        ->and($props['icons'])->toBe(['draft' => 'pencil']);
});

it('BadgeColumn: empty maps are omitted from the payload', function (): void {
    $props = BadgeColumn::make('status')->getTypeSpecificProps();

    expect($props)->not->toHaveKey('colors')
        ->and($props)->not->toHaveKey('icons');
});

it('BooleanColumn: defaults to check/x without colours', function (): void {
    $props = BooleanColumn::make('is_active')->getTypeSpecificProps();

    expect($props)->toBe([
        'trueIcon' => 'check',
        'falseIcon' => 'x',
    ]);
});

it('BooleanColumn: serialises custom icons and colours', function (): void {
    $props = BooleanColumn::make('is_published')
        ->trueIcon('eye')
        ->falseIcon('eye-slash')
        ->trueColor('emerald')
        ->falseColor('zinc')
        ->getTypeSpecificProps();

    expect($props)->toBe([
        'trueIcon' => 'eye',
        'falseIcon' => 'eye-slash',
        'trueColor' => 'emerald',
        'falseColor' => 'zinc',
    ]);
});

it('DateColumn: switches between date / dateTime / since modes', function (): void {
    expect(DateColumn::make('created_at')->getTypeSpecificProps())
        ->toBe(['mode' => 'date', 'format' => 'Y-m-d'])
        ->and(DateColumn::make('updated_at')->dateTime('d/m/Y H:i')->getTypeSpecificProps())
        ->toBe(['mode' => 'datetime', 'format' => 'd/m/Y H:i'])
        ->and(DateColumn::make('published_at')->since()->getTypeSpecificProps())
        ->toBe(['mode' => 'since', 'format' => 'Y-m-d']);
});

it('NumberColumn: serialises currency, prefix, suffix, and decimals only when set', function (): void {
    $bare = NumberColumn::make('amount')->getTypeSpecificProps();
    $full = NumberColumn::make('amount')
        ->money('USD')
        ->prefix('US$')
        ->suffix(' total')
        ->decimals(2)
        ->getTypeSpecificProps();

    expect($bare)->toBe([])
        ->and($full)->toBe([
            'currency' => 'USD',
            'prefix' => 'US$',
            'suffix' => ' total',
            'decimals' => 2,
        ]);
});

it('IconColumn: options map and size are filtered when not set', function (): void {
    $props = IconColumn::make('priority')
        ->options(['high' => 'arrow-up', 'low' => 'arrow-down'])
        ->size('lg')
        ->getTypeSpecificProps();

    expect($props)->toBe([
        'options' => ['high' => 'arrow-up', 'low' => 'arrow-down'],
        'size' => 'lg',
    ]);
});

it('ImageColumn: defaults to square shape and serialises disk/directory/size', function (): void {
    $props = ImageColumn::make('avatar')
        ->disk('public')
        ->directory('avatars')
        ->circular()
        ->size(48)
        ->getTypeSpecificProps();

    expect($props)->toBe([
        'disk' => 'public',
        'directory' => 'avatars',
        'shape' => 'circular',
        'size' => 48,
    ])
        ->and(ImageColumn::make('avatar')->getTypeSpecificProps()['shape'])->toBe('square');
});

it('RelationshipColumn: getState walks the relation and returns the display attribute', function (): void {
    $related = new class extends Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];

        public string $name = 'Acme';
    };
    $related->setRawAttributes(['name' => 'Acme']);

    $record = new class extends Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];
    };
    $record->setRelation('team', $related);

    $column = RelationshipColumn::make('team')->display('name');

    expect($column->getType())->toBe('relationship')
        ->and($column->getDisplayAttribute())->toBe('name')
        ->and($column->getState($record))->toBe('Acme')
        ->and($column->getState(null))->toBeNull();
});

it('ComputedColumn: state is produced by getStateUsing, not by attribute lookup', function (): void {
    $record = new class extends Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];
    };
    $record->setRawAttributes(['first' => 'Ada', 'last' => 'Lovelace']);

    $column = ComputedColumn::make('full_name')
        ->getStateUsing(fn ($r) => trim($r->first.' '.$r->last));

    expect($column->getType())->toBe('computed')
        ->and($column->isSortable())->toBeFalse()
        ->and($column->getState($record))->toBe('Ada Lovelace');
});

it('Column.canSee gates per-record visibility', function (): void {
    $hiddenColumn = TextColumn::make('admin_notes')
        ->canSee(fn () => false);

    expect($hiddenColumn->isVisibleFor())->toBeFalse();

    $shown = TextColumn::make('name');

    expect($shown->isVisibleFor())->toBeTrue();

    $hidden = TextColumn::make('legacy')->hidden();
    expect($hidden->isVisibleFor())->toBeFalse();
});

it('Column.url accepts both literal strings and Closures', function (): void {
    $literal = TextColumn::make('homepage')->url('https://arqel.dev');
    $closure = TextColumn::make('homepage')->url(fn () => 'https://example.com', newTab: true);

    expect($literal->resolveUrl())->toBe('https://arqel.dev')
        ->and($closure->resolveUrl())->toBe('https://example.com');
});

it('formatStateUsing wraps the raw value', function (): void {
    $column = TextColumn::make('name')
        ->formatStateUsing(fn ($state) => strtoupper((string) $state));

    expect($column->formatState('alice'))->toBe('ALICE');
});
