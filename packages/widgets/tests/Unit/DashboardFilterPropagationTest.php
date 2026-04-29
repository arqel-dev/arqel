<?php

declare(strict_types=1);

use Arqel\Widgets\Dashboard;
use Arqel\Widgets\Filters\SelectFilter;
use Arqel\Widgets\Tests\Fixtures\EchoFiltersWidget;
use Illuminate\Container\Container;

beforeEach(function (): void {
    Container::setInstance(new Container);
});

it('declared filters propagate their defaults into every widget at resolve() time', function (): void {
    $a = new EchoFiltersWidget('a');
    $b = new EchoFiltersWidget('b');

    $payload = Dashboard::make('analytics', 'Analytics')
        ->filters([SelectFilter::make('segment')->default('all')])
        ->widgets([$a, $b])
        ->resolve();

    expect($payload['widgets'])->toHaveCount(2)
        ->and($payload['widgets'][0]['data'])->toBe(['filters' => ['segment' => 'all']])
        ->and($payload['widgets'][1]['data'])->toBe(['filters' => ['segment' => 'all']])
        ->and($payload['filters'])->toBe(['segment' => 'all']);
});

it('pre-applied widget filter values override dashboard defaults', function (): void {
    $widget = (new EchoFiltersWidget('a'))->filters(['segment' => 'active']);

    $payload = Dashboard::make('analytics', 'Analytics')
        ->filters([SelectFilter::make('segment')->default('all')])
        ->widgets([$widget])
        ->resolve();

    expect($payload['widgets'][0]['data'])->toBe(['filters' => ['segment' => 'active']]);
});

it('multiple declared filters all propagate; widget-specific overrides remain isolated', function (): void {
    $widgetA = new EchoFiltersWidget('a');
    $widgetB = (new EchoFiltersWidget('b'))->filters(['segment' => 'active']);

    $payload = Dashboard::make('analytics', 'Analytics')
        ->filters([
            SelectFilter::make('segment')->default('all'),
            SelectFilter::make('region')->default('us'),
        ])
        ->widgets([$widgetA, $widgetB])
        ->resolve();

    expect($payload['widgets'][0]['data']['filters'])->toBe([
        'segment' => 'all',
        'region' => 'us',
    ])->and($payload['widgets'][1]['data']['filters'])->toBe([
        'segment' => 'active',
        'region' => 'us',
    ]);
});

it('legacy array<string, mixed> filters still propagate as passthrough metadata', function (): void {
    $widget = new EchoFiltersWidget('a');

    $payload = Dashboard::make('t', 'T')
        ->filters(['custom' => 'value', 'date' => ['type' => 'range']])
        ->widgets([$widget])
        ->resolve();

    expect($payload['filters'])->toBe(['custom' => 'value', 'date' => ['type' => 'range']])
        ->and($payload['widgets'][0]['data']['filters'])->toBe([
            'custom' => 'value',
            'date' => ['type' => 'range'],
        ]);
});

it('legacy mode does not populate declaredFilters / filterDefaults', function (): void {
    $dash = Dashboard::make('t', 'T')->filters(['custom' => 'value']);

    expect($dash->getDeclaredFilters())->toBe([])
        ->and($dash->getFilterDefaults())->toBe([])
        ->and($dash->getFilters())->toBe(['custom' => 'value']);
});

it('declarative mode populates declaredFilters and filterDefaults', function (): void {
    $segment = SelectFilter::make('segment')->default('all');
    $region = SelectFilter::make('region')->default('us');

    $dash = Dashboard::make('t', 'T')->filters([$segment, $region]);

    expect($dash->getDeclaredFilters())->toBe([$segment, $region])
        ->and($dash->getFilterDefaults())->toBe(['segment' => 'all', 'region' => 'us'])
        ->and($dash->getFilters())->toBe(['segment' => 'all', 'region' => 'us']);
});

it('Widget::filterValue reads from the merged filter map with fallback', function (): void {
    $widget = new EchoFiltersWidget('a');

    Dashboard::make('t', 'T')
        ->filters([SelectFilter::make('segment')->default('all')])
        ->widgets([$widget])
        ->resolve();

    expect($widget->filterValue('segment'))->toBe('all')
        ->and($widget->filterValue('missing', 'fallback'))->toBe('fallback')
        ->and($widget->filterValue('missing'))->toBeNull();
});
