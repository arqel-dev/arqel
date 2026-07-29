<?php

declare(strict_types=1);

use Arqel\Core\Panel\PanelRegistry;
use Arqel\Widgets\Dashboard;
use Arqel\Widgets\DashboardRegistry;
use Arqel\Widgets\Tests\Fixtures\CounterWidget;
use Arqel\Widgets\Tests\Fixtures\SecondaryWidget;
use Arqel\Widgets\Tests\Fixtures\WidgetPlugin;
use Arqel\Widgets\WidgetsServiceProvider;

/**
 * O bridge 0.19b: widgets declarados num Panel precisam chegar ao
 * DashboardRegistry, senão `Panel::widgets()` é um campo órfão.
 *
 * O provider já bootou quando o teste roda, então cada caso reinvoca
 * o hook por reflection — mesmo padrão de PanelToRegistrySyncTest no core.
 */
function invokeWidgetSync(): void
{
    $provider = app()->getProvider(WidgetsServiceProvider::class);
    // `setAccessible()` é dispensável desde o PHP 8.1 — a reflection já
    // alcança membros protegidos — e está deprecada no 8.4.
    $method = new ReflectionMethod($provider, 'syncPanelWidgetsIntoDashboardRegistry');
    $method->invoke($provider);
}

beforeEach(function (): void {
    app(PanelRegistry::class)->clear();
    app(DashboardRegistry::class)->clear();
});

it('creates the main dashboard from panel widgets when none is registered', function (): void {
    app(PanelRegistry::class)->panel('admin')->widgets([CounterWidget::class]);

    invokeWidgetSync();

    $dashboard = app(DashboardRegistry::class)->get('main');

    expect($dashboard)->not->toBeNull()
        ->and($dashboard->getWidgets())->toBe([CounterWidget::class]);
});

it('registers no dashboard when no panel declares widgets', function (): void {
    app(PanelRegistry::class)->panel('admin');

    invokeWidgetSync();

    expect(app(DashboardRegistry::class)->has('main'))->toBeFalse();
});

it('registers no dashboard when there are no panels at all', function (): void {
    invokeWidgetSync();

    expect(app(DashboardRegistry::class)->all())->toBe([]);
});

it('appends panel widgets to a dashboard the app already registered', function (): void {
    // A aplicação registra o seu dashboard primeiro — o caso de demo/showcase.
    app(DashboardRegistry::class)->register(
        Dashboard::make('main', 'App Dashboard')->widgets([SecondaryWidget::class]),
    );

    app(PanelRegistry::class)->panel('admin')->widgets([CounterWidget::class]);

    invokeWidgetSync();

    $widgets = app(DashboardRegistry::class)->get('main')->getWidgets();

    // O widget da app continua presente — nada de clobbering.
    expect($widgets)->toContain(SecondaryWidget::class)
        ->and($widgets)->toContain(CounterWidget::class)
        ->and($widgets)->toHaveCount(2);
});

it('keeps the label the app chose when merging', function (): void {
    app(DashboardRegistry::class)->register(
        Dashboard::make('main', 'App Dashboard'),
    );
    app(PanelRegistry::class)->panel('admin')->widgets([CounterWidget::class]);

    invokeWidgetSync();

    expect(app(DashboardRegistry::class)->get('main')->label)->toBe('App Dashboard');
});

it('picks up widgets a plugin adds during boot', function (): void {
    $panel = app(PanelRegistry::class)->panel('admin')->widgets([CounterWidget::class]);

    // Simula o que `bootPanelPlugins()` faz no core: o plugin muta o
    // Panel antes de o sync de widgets rodar.
    WidgetPlugin::make()->boot($panel);

    invokeWidgetSync();

    $widgets = app(DashboardRegistry::class)->get('main')->getWidgets();

    expect($widgets)->toContain(CounterWidget::class)
        ->and($widgets)->toContain(SecondaryWidget::class);
});
