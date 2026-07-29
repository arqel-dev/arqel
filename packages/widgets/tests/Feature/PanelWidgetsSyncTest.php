<?php

declare(strict_types=1);

use Arqel\Core\Panel\PanelRegistry;
use Arqel\Widgets\DashboardRegistry;
use Arqel\Widgets\Tests\Fixtures\CounterWidget;
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
