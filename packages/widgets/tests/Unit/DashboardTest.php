<?php

declare(strict_types=1);

use Arqel\Widgets\Dashboard;
use Arqel\Widgets\Tests\Fixtures\CounterWidget;
use Arqel\Widgets\Widget;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;

beforeEach(function (): void {
    Container::setInstance(new Container);
});

it('constructor sets readonly id/label/path and factory mirrors it', function (): void {
    $direct = new Dashboard('main', 'Main', '/main');
    expect($direct->id)->toBe('main')
        ->and($direct->label)->toBe('Main')
        ->and($direct->path)->toBe('/main');

    $made = Dashboard::make('reports', 'Reports');
    expect($made->id)->toBe('reports')
        ->and($made->label)->toBe('Reports')
        ->and($made->path)->toBeNull();
});

it('starts empty with default 12 columns', function (): void {
    $dash = Dashboard::make('test', 'Test');

    expect($dash->getWidgets())->toBe([])
        ->and($dash->getColumns())->toBe(12)
        ->and($dash->getFilters())->toBe([]);
});

it('widgets() accepts both Widget instances and class-strings', function (): void {
    $instance = new CounterWidget('a');

    $dash = Dashboard::make('test', 'Test')->widgets([
        $instance,
        CounterWidget::class,
    ]);

    expect($dash->getWidgets())->toHaveCount(2)
        ->and($dash->getWidgets()[0])->toBe($instance)
        ->and($dash->getWidgets()[1])->toBe(CounterWidget::class);
});

it('widgets() filters non-Widget and non-class-string entries silently', function (): void {
    $dash = Dashboard::make('test', 'Test')->widgets([
        new CounterWidget('a'),
        'not-a-widget',
        stdClass::class,
        new CounterWidget('b'),
        CounterWidget::class,
        42,
    ]);

    expect($dash->getWidgets())->toHaveCount(3);
});

it('addWidget appends a Widget instance', function (): void {
    $dash = Dashboard::make('t', 'T')->addWidget(new CounterWidget('a'));
    $dash->addWidget(new CounterWidget('b'));

    expect($dash->getWidgets())->toHaveCount(2);
});

it('addWidget accepts a class-string', function (): void {
    $dash = Dashboard::make('t', 'T')->addWidget(CounterWidget::class);

    expect($dash->getWidgets())->toBe([CounterWidget::class]);
});

it('addWidget ignores invalid class-strings silently', function (): void {
    $dash = Dashboard::make('t', 'T')->addWidget(stdClass::class);

    expect($dash->getWidgets())->toBe([]);
});

it('columns(int) clamps to 1..12', function (): void {
    expect(Dashboard::make('t', 'T')->columns(0)->getColumns())->toBe(1)
        ->and(Dashboard::make('t', 'T')->columns(20)->getColumns())->toBe(12)
        ->and(Dashboard::make('t', 'T')->columns(6)->getColumns())->toBe(6);
});

it('columns(array) accepts a responsive map and clamps each value', function (): void {
    $cols = Dashboard::make('t', 'T')->columns([
        'sm' => 0,
        'md' => 2,
        'lg' => 99,
        'xl' => 4,
    ])->getColumns();

    expect($cols)->toBe([
        'sm' => 1,
        'md' => 2,
        'lg' => 12,
        'xl' => 4,
    ]);
});

it('columns(array) drops unknown keys and non-int values silently', function (): void {
    $cols = Dashboard::make('t', 'T')->columns([
        'sm' => 2,
        'wat' => 4,
        'lg' => 'three',
        'xl' => 6,
    ])->getColumns();

    expect($cols)->toBe(['sm' => 2, 'xl' => 6]);
});

it('filters() stores a passthrough array', function (): void {
    $dash = Dashboard::make('t', 'T')->filters([
        'date' => ['type' => 'range'],
        'status' => ['type' => 'select', 'options' => ['active', 'archived']],
    ]);

    expect($dash->getFilters())->toBe([
        'date' => ['type' => 'range'],
        'status' => ['type' => 'select', 'options' => ['active', 'archived']],
    ]);
});

it('canBeSeenBy returns true by default and delegates to the closure when set', function (): void {
    $dash = Dashboard::make('t', 'T');
    expect($dash->canBeSeenBy(null))->toBeTrue();

    $dash->canSee(fn (?Authenticatable $user) => $user !== null);
    expect($dash->canBeSeenBy(null))->toBeFalse()
        ->and($dash->canBeSeenBy(new User))->toBeTrue();
});

it('resolve() returns the canonical 8-key shape', function (): void {
    $dash = Dashboard::make('main', 'Main', '/main')
        ->columns(6)
        ->heading('Overview')
        ->description('KPIs')
        ->filters(['date' => ['type' => 'range']]);

    $payload = $dash->resolve();

    expect(array_keys($payload))->toEqualCanonicalizing([
        'id', 'label', 'path', 'widgets', 'filters', 'columns', 'heading', 'description',
    ])
        ->and($payload['id'])->toBe('main')
        ->and($payload['label'])->toBe('Main')
        ->and($payload['path'])->toBe('/main')
        ->and($payload['widgets'])->toBe([])
        ->and($payload['filters'])->toBe(['date' => ['type' => 'range']])
        ->and($payload['columns'])->toBe(6)
        ->and($payload['heading'])->toBe('Overview')
        ->and($payload['description'])->toBe('KPIs');
});

it('resolve() instantiates class-string widgets via the container', function (): void {
    Container::getInstance()->bind(CounterWidget::class, fn () => new CounterWidget('from-container'));

    $payload = Dashboard::make('t', 'T')
        ->widgets([CounterWidget::class])
        ->resolve();

    expect($payload['widgets'])->toHaveCount(1)
        ->and($payload['widgets'][0]['name'])->toBe('from-container');
});

it('resolve() filters by canBeSeenBy and sorts by getSort', function (): void {
    $a = (new CounterWidget('a'))->sort(20);
    $b = (new CounterWidget('b'))->sort(10);
    $c = (new CounterWidget('c'))->canSee(fn () => false);
    $d = new CounterWidget('d'); // null sort — last

    $payload = Dashboard::make('t', 'T')
        ->widgets([$a, $b, $c, $d])
        ->resolve();

    $names = array_column($payload['widgets'], 'name');
    expect($names)->toBe(['b', 'a', 'd']);
});

it('resolve() honours canBeSeenBy(user) when given a user', function (): void {
    $widget = (new CounterWidget('admin-only'))->canSee(fn (?Authenticatable $user) => $user !== null);
    $dash = Dashboard::make('t', 'T')->widgets([$widget]);

    expect($dash->resolve(null)['widgets'])->toBe([])
        ->and($dash->resolve(new User)['widgets'])->toHaveCount(1);
});

it('resolve() skips entries that fail to resolve to a Widget', function (): void {
    // Bind class-string to something that throws on construction.
    Container::getInstance()->bind(CounterWidget::class, function (): Widget {
        throw new RuntimeException('cannot build');
    });

    $payload = Dashboard::make('t', 'T')
        ->widgets([CounterWidget::class, new CounterWidget('ok')])
        ->resolve();

    expect($payload['widgets'])->toHaveCount(1)
        ->and($payload['widgets'][0]['name'])->toBe('ok');
});

it('toArray() returns identical shape to resolve()', function (): void {
    $widget = new CounterWidget('a');

    $dash = Dashboard::make('main', 'Main', '/main')
        ->widgets([$widget])
        ->columns(['sm' => 1, 'lg' => 4])
        ->heading('Overview')
        ->description('Stuff')
        ->filters(['x' => 1]);

    expect($dash->toArray())->toBe($dash->resolve());
});
