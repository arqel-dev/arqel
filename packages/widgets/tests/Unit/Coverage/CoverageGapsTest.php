<?php

declare(strict_types=1);

use Arqel\Widgets\Dashboard;
use Arqel\Widgets\Filters\SelectFilter;
use Arqel\Widgets\Tests\Fixtures\CounterWidget;
use Arqel\Widgets\Tests\Fixtures\EchoFiltersWidget;
use Arqel\Widgets\WidgetRegistry;
use Illuminate\Container\Container;

beforeEach(function (): void {
    Container::setInstance(new Container);
});

it('WidgetRegistry::register overwrites an existing type silently (last write wins)', function (): void {
    $registry = new WidgetRegistry;
    $registry->register('counter', CounterWidget::class);
    $registry->register('counter', EchoFiltersWidget::class);

    expect($registry->get('counter'))->toBe(EchoFiltersWidget::class)
        ->and($registry->all())->toHaveCount(1);
});

it('WidgetRegistry::register throws when given a non-existent class-string', function (): void {
    $registry = new WidgetRegistry;

    expect(fn () => $registry->register('ghost', 'App\\Widgets\\NotARealClass'))
        ->toThrow(InvalidArgumentException::class);
});

it('Dashboard::filters switching from declarative back to legacy clears declared metadata', function (): void {
    $dash = Dashboard::make('t', 'T')
        ->filters([SelectFilter::make('segment')->default('all')]);

    expect($dash->getDeclaredFilters())->toHaveCount(1)
        ->and($dash->getFilterDefaults())->toBe(['segment' => 'all']);

    // Switch back to legacy passthrough mode.
    $dash->filters(['custom' => 'value']);

    expect($dash->getDeclaredFilters())->toBe([])
        ->and($dash->getFilterDefaults())->toBe([])
        ->and($dash->getFilters())->toBe(['custom' => 'value']);
});

it('partial widget filter set merges with dashboard defaults (per-key fallback)', function (): void {
    // Widget declares only `segment`; dashboard provides defaults for both
    // `segment` and `region`. Expect widget's `segment` to win, `region`
    // to fall back to the dashboard default.
    $widget = (new EchoFiltersWidget('a'))->filters(['segment' => 'active']);

    $payload = Dashboard::make('analytics', 'Analytics')
        ->filters([
            SelectFilter::make('segment')->default('all'),
            SelectFilter::make('region')->default('us'),
        ])
        ->widgets([$widget])
        ->resolve();

    expect($payload['widgets'][0]['data']['filters'])->toBe([
        'segment' => 'active',
        'region' => 'us',
    ]);
});

it('Dashboard::addWidget after widgets() appends rather than replacing', function (): void {
    $dash = Dashboard::make('t', 'T')
        ->widgets([new CounterWidget('a'), new CounterWidget('b')])
        ->addWidget(new CounterWidget('c'));

    expect($dash->getWidgets())->toHaveCount(3);
});

it('Widget::filterValue returns the supplied default when no filters were ever set', function (): void {
    $widget = new EchoFiltersWidget('orphan');

    expect($widget->filterValue('missing'))->toBeNull()
        ->and($widget->filterValue('missing', 'fallback'))->toBe('fallback')
        ->and($widget->getFilters())->toBe([]);
});

it('Dashboard::resolve drops every entry when none materialise to a Widget', function (): void {
    Container::getInstance()->bind(CounterWidget::class, function (): never {
        throw new RuntimeException('cannot build');
    });

    $payload = Dashboard::make('t', 'T')
        ->widgets([CounterWidget::class, CounterWidget::class])
        ->resolve();

    expect($payload['widgets'])->toBe([]);
});
