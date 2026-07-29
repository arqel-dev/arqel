<?php

declare(strict_types=1);

use Arqel\Core\ArqelServiceProvider;
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

it('runs the widget sync after core has booted panel plugins', function (): void {
    // Integração de verdade: em vez de chamar `Plugin::boot()` à mão, dispara
    // os hooks dos DOIS providers na ordem de produção — core primeiro, que é
    // onde `bootPanelPlugins()` vive, e widgets depois.
    //
    // Se o sync de widgets rodasse antes, o widget que só existe a partir do
    // boot do plugin nunca chegaria ao dashboard, e a asserção falharia.
    app(PanelRegistry::class)->panel('admin')
        ->plugin(WidgetPlugin::make())
        ->widgets([CounterWidget::class]);

    $core = app()->getProvider(ArqelServiceProvider::class);
    $bootPlugins = new ReflectionMethod($core, 'bootPanelPlugins');
    $bootPlugins->invoke($core);

    invokeWidgetSync();

    $widgets = app(DashboardRegistry::class)->get('main')->getWidgets();

    expect($widgets)->toContain(CounterWidget::class)
        ->and($widgets)->toContain(SecondaryWidget::class);
});

it('silently drops entries that are not widgets', function (): void {
    app(PanelRegistry::class)->panel('admin')->widgets([
        CounterWidget::class,
        'App\\Does\\Not\\Exist',
        stdClass::class,
    ]);

    invokeWidgetSync();

    // `Dashboard::widgets()`/`addWidget()` filtram non-Widget: má
    // configuração não derruba o boot do painel.
    expect(app(DashboardRegistry::class)->get('main')->getWidgets())
        ->toBe([CounterWidget::class]);
});

it('collects widgets from every registered panel', function (): void {
    $panels = app(PanelRegistry::class);
    $panels->panel('admin')->widgets([CounterWidget::class]);
    $panels->panel('reports')->widgets([SecondaryWidget::class]);

    invokeWidgetSync();

    // Documenta a consequência do multi-panel: tudo converge para `main`,
    // porque não existe vínculo panel↔dashboard (spec, "Quais panels são lidos").
    //
    // A asserção nomeia os widgets em vez de só contar: um simples
    // `toHaveCount(2)` passaria mesmo se o sync duplicasse um panel e
    // perdesse o outro.
    expect(app(DashboardRegistry::class)->get('main')->getWidgets())
        ->toBe([CounterWidget::class, SecondaryWidget::class]);
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
