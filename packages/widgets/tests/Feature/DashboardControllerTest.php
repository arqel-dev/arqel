<?php

declare(strict_types=1);

use Arqel\Widgets\Dashboard;
use Arqel\Widgets\DashboardRegistry;
use Arqel\Widgets\Http\Controllers\DashboardController;
use Arqel\Widgets\Tests\Fixtures\CounterWidget;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
 * The Inertia middleware stack registered by ArqelServiceProvider's
 * `web` group fails to resolve `Inertia\Ssr\Gateway` in this minimal
 * testbench setup (same gotcha noted in TENANT-009). The
 * controller's logic does not depend on the middleware stack, so we
 * invoke `show()` directly and inspect the returned `Inertia\Response`
 * via reflection for prop assertions.
 */

/**
 * @return array<string, mixed>
 */
function readInertiaProps(InertiaResponse $response): array
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('props');
    $property->setAccessible(true);

    /** @var array<string, mixed> $props */
    $props = $property->getValue($response);

    return $props;
}

beforeEach(function (): void {
    /** @var DashboardRegistry $registry */
    $registry = app(DashboardRegistry::class);
    $registry->clear();
});

it('aborts with 404 when the dashboard id is unknown', function (): void {
    $controller = new DashboardController;
    $registry = app(DashboardRegistry::class);
    $request = Request::create('/admin/dashboards/nope', 'GET');

    expect(fn () => $controller->show($request, $registry, 'nope'))
        ->toThrow(NotFoundHttpException::class);
});

it('renders the resolved dashboard payload via Inertia', function (): void {
    /** @var DashboardRegistry $registry */
    $registry = app(DashboardRegistry::class);

    $dashboard = Dashboard::make('main', 'Main Dashboard')
        ->widgets([new CounterWidget('hits')]);

    $registry->register($dashboard);

    $controller = new DashboardController;
    $request = Request::create('/admin', 'GET');

    $response = $controller->show($request, $registry);

    expect($response)->toBeInstanceOf(InertiaResponse::class);

    $props = readInertiaProps($response);

    expect($props)->toHaveKey('dashboard');
    expect($props['dashboard'])->toBeArray();
    expect($props['dashboard']['id'])->toBe('main');
    expect($props['dashboard']['widgets'])->toBeArray()->toHaveCount(1);
});

it('defaults the dashboard id to "main" when null is passed', function (): void {
    /** @var DashboardRegistry $registry */
    $registry = app(DashboardRegistry::class);
    $registry->register(Dashboard::make('main', 'Main'));

    $controller = new DashboardController;
    $request = Request::create('/admin', 'GET');

    $response = $controller->show($request, $registry, null);

    $props = readInertiaProps($response);

    expect($props['dashboard']['id'])->toBe('main');
});

it('passes the filters query parameter through as filterValues', function (): void {
    /** @var DashboardRegistry $registry */
    $registry = app(DashboardRegistry::class);
    $registry->register(Dashboard::make('main', 'Main'));

    $controller = new DashboardController;
    $request = Request::create('/admin?filters[range]=7d', 'GET');

    $response = $controller->show($request, $registry);

    $props = readInertiaProps($response);

    expect($props)->toHaveKey('filterValues');
    expect($props['filterValues'])->toBe(['range' => '7d']);
});

it('resolves dashboards with a custom id via the explicit route param', function (): void {
    /** @var DashboardRegistry $registry */
    $registry = app(DashboardRegistry::class);

    $registry->register(Dashboard::make('sales', 'Sales'));
    $registry->register(Dashboard::make('main', 'Main'));

    $controller = new DashboardController;
    $request = Request::create('/admin/dashboards/sales', 'GET');

    $response = $controller->show($request, $registry, 'sales');

    $props = readInertiaProps($response);

    expect($props['dashboard']['id'])->toBe('sales');
    expect($props['dashboard']['label'])->toBe('Sales');
});
