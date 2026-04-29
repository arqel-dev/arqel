<?php

declare(strict_types=1);

use Arqel\Widgets\ChartWidget;

it('exposes the canonical type + component', function (): void {
    $widget = ChartWidget::make('sales');

    expect($widget->getType())->toBe('chart')
        ->and($widget->getComponent())->toBe('ChartWidget');
});

it('chartType accepts canonical values', function (string $type): void {
    expect(ChartWidget::make('x')->chartType($type)->getChartType())->toBe($type);
})->with(['line', 'bar', 'area', 'pie', 'donut', 'radar']);

it('chartType falls back to line for unknown values', function (): void {
    expect(ChartWidget::make('x')->chartType('flux-capacitor')->getChartType())->toBe('line')
        ->and(ChartWidget::make('x')->chartType('')->getChartType())->toBe('line');
});

it('exposes CHART_* constants matching canonical values', function (): void {
    expect(ChartWidget::CHART_LINE)->toBe('line')
        ->and(ChartWidget::CHART_BAR)->toBe('bar')
        ->and(ChartWidget::CHART_AREA)->toBe('area')
        ->and(ChartWidget::CHART_PIE)->toBe('pie')
        ->and(ChartWidget::CHART_DONUT)->toBe('donut')
        ->and(ChartWidget::CHART_RADAR)->toBe('radar');
});

it('height clamps to ≥ 50 and defaults to 300', function (): void {
    expect(ChartWidget::make('x')->getHeight())->toBe(300)
        ->and(ChartWidget::make('x')->height(10)->getHeight())->toBe(50)
        ->and(ChartWidget::make('x')->height(0)->getHeight())->toBe(50)
        ->and(ChartWidget::make('x')->height(-100)->getHeight())->toBe(50)
        ->and(ChartWidget::make('x')->height(800)->getHeight())->toBe(800);
});

it('showLegend and showGrid default to true and toggle', function (): void {
    $widget = ChartWidget::make('x');

    expect($widget->isLegendVisible())->toBeTrue()
        ->and($widget->isGridVisible())->toBeTrue()
        ->and($widget->showLegend(false)->isLegendVisible())->toBeFalse()
        ->and($widget->showGrid(false)->isGridVisible())->toBeFalse()
        ->and($widget->showLegend()->isLegendVisible())->toBeTrue()
        ->and($widget->showGrid()->isGridVisible())->toBeTrue();
});

it('chartData passes arrays through unchanged', function (): void {
    $payload = ['labels' => ['Jan', 'Feb'], 'datasets' => [['label' => 'A', 'data' => [1, 2]]]];

    expect(ChartWidget::make('x')->chartData($payload)->data()['chartData'])->toBe($payload);
});

it('chartData resolves Closures lazily', function (): void {
    $invoked = 0;
    $widget = ChartWidget::make('x')->chartData(function () use (&$invoked) {
        $invoked++;

        return ['labels' => ['A'], 'datasets' => []];
    });

    expect($invoked)->toBe(0);
    expect($widget->data()['chartData'])->toBe(['labels' => ['A'], 'datasets' => []]);
    expect($invoked)->toBe(1);
});

it('chartData Closure returning non-array falls back to empty shape', function (): void {
    $widget = ChartWidget::make('x')->chartData(fn () => 'oops');

    expect($widget->data()['chartData'])->toBe(['labels' => [], 'datasets' => []]);
});

it('chartOptions arrays pass through and Closures resolve', function (): void {
    $array = ['stroke' => '#f00'];
    expect(ChartWidget::make('x')->chartOptions($array)->data()['chartOptions'])->toBe($array);

    $widget = ChartWidget::make('x')->chartOptions(fn () => ['fill' => '#0f0']);
    expect($widget->data()['chartOptions'])->toBe(['fill' => '#0f0']);
});

it('chartOptions Closure returning non-array falls back to []', function (): void {
    $widget = ChartWidget::make('x')->chartOptions(fn () => 'oops');

    expect($widget->data()['chartOptions'])->toBe([]);
});

it('data() returns the canonical envelope', function (): void {
    $widget = ChartWidget::make('x')
        ->chartType('bar')
        ->height(420)
        ->showLegend(false)
        ->showGrid(false)
        ->chartData(['labels' => ['a'], 'datasets' => []])
        ->chartOptions(['k' => 'v']);

    expect($widget->data())->toBe([
        'chartType' => 'bar',
        'chartData' => ['labels' => ['a'], 'datasets' => []],
        'chartOptions' => ['k' => 'v'],
        'height' => 420,
        'showLegend' => false,
        'showGrid' => false,
    ]);
});

it('toArray emits chart data inline by default', function (): void {
    $payload = ChartWidget::make('sales')
        ->chartType('line')
        ->chartData(['labels' => ['Q1'], 'datasets' => [['label' => 'A', 'data' => [10]]]])
        ->toArray();

    expect($payload['type'])->toBe('chart')
        ->and($payload['component'])->toBe('ChartWidget')
        ->and($payload['data']['chartType'])->toBe('line')
        ->and($payload['data']['chartData']['labels'])->toBe(['Q1']);
});

it('deferred ChartWidget emits data: null and does not invoke Closures', function (): void {
    $invoked = 0;
    $widget = ChartWidget::make('x')
        ->deferred()
        ->chartData(function () use (&$invoked) {
            $invoked++;

            return ['labels' => [], 'datasets' => []];
        })
        ->chartOptions(function () use (&$invoked) {
            $invoked++;

            return [];
        });

    $payload = $widget->toArray();

    expect($payload['deferred'])->toBeTrue()
        ->and($payload['data'])->toBeNull()
        ->and($invoked)->toBe(0);
});
