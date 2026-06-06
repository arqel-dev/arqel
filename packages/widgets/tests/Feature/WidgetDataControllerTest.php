<?php

declare(strict_types=1);

use Arqel\Widgets\Dashboard;
use Arqel\Widgets\DashboardRegistry;
use Arqel\Widgets\Filters\SelectFilter;
use Arqel\Widgets\Http\Controllers\WidgetDataController;
use Arqel\Widgets\Tests\Fixtures\EchoFiltersWidget;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Feature tests invoke the controller directly to side-step the
 * Inertia SSR Gateway resolution issue we hit in DashboardController
 * tests under testbench. The behaviour we care about here lives in
 * the controller body; the route plumbing is exercised in an
 * integration test (out of scope for the scaffold layer).
 */
beforeEach(function (): void {
    $this->registry = new DashboardRegistry;
});

it('returns 404 when the dashboard id is unknown', function (): void {
    $controller = new WidgetDataController;
    $request = Request::create('/admin/dashboards/missing/widgets/anything/data', 'GET');

    expect(fn () => $controller->show($request, $this->registry, 'missing', 'anything'))
        ->toThrow(HttpException::class);
});

it('returns 404 when the widget id is unknown but dashboard exists', function (): void {
    $dashboard = Dashboard::make('main', 'Main')
        ->widgets([(new EchoFiltersWidget('totals'))]);
    $this->registry->register($dashboard);

    $controller = new WidgetDataController;
    $request = Request::create('/admin/dashboards/main/widgets/missing/data', 'GET');

    expect(fn () => $controller->show($request, $this->registry, 'main', 'missing'))
        ->toThrow(HttpException::class);
});

it('returns 403 when the dashboard canSee() denies the user', function (): void {
    $widget = new EchoFiltersWidget('totals');
    $dashboard = Dashboard::make('main', 'Main')
        ->widgets([$widget])
        ->canSee(fn () => false);
    $this->registry->register($dashboard);

    $controller = new WidgetDataController;
    $request = Request::create('/admin/dashboards/main/widgets/echo:totals/data', 'GET');

    expect(fn () => $controller->show($request, $this->registry, 'main', 'echo:totals'))
        ->toThrow(HttpException::class);
});

it('seeds declared dashboard filter defaults when the request supplies no filters', function (): void {
    $widget = new EchoFiltersWidget('totals');
    $dashboard = Dashboard::make('main', 'Main')
        ->filters([SelectFilter::make('segment')->default('all')])
        ->widgets([$widget]);
    $this->registry->register($dashboard);

    $controller = new WidgetDataController;
    $request = Request::create('/admin/dashboards/main/widgets/echo:totals/data', 'GET');

    $response = $controller->show($request, $this->registry, 'main', 'echo:totals');
    $payload = $response->getData(true);

    expect($payload['data']['filters'])->toBe(['segment' => 'all']);
});

it('lets request filters win over declared dashboard filter defaults', function (): void {
    $widget = new EchoFiltersWidget('totals');
    $dashboard = Dashboard::make('main', 'Main')
        ->filters([SelectFilter::make('segment')->default('all')])
        ->widgets([$widget]);
    $this->registry->register($dashboard);

    $controller = new WidgetDataController;
    $request = Request::create(
        '/admin/dashboards/main/widgets/echo:totals/data',
        'GET',
        ['filters' => ['segment' => 'active']],
    );

    $response = $controller->show($request, $this->registry, 'main', 'echo:totals');
    $payload = $response->getData(true);

    expect($payload['data']['filters'])->toBe(['segment' => 'active']);
});

it('returns 403 when canBeSeenBy rejects the user', function (): void {
    $widget = (new EchoFiltersWidget('blocked'))->canSee(fn () => false);
    $dashboard = Dashboard::make('main', 'Main')->widgets([$widget]);
    $this->registry->register($dashboard);

    $controller = new WidgetDataController;
    $request = Request::create('/admin/dashboards/main/widgets/echo:blocked/data', 'GET');

    expect(fn () => $controller->show($request, $this->registry, 'main', 'echo:blocked'))
        ->toThrow(HttpException::class);
});

it('returns the widget data payload as JSON on the happy path', function (): void {
    $widget = new EchoFiltersWidget('totals');
    $dashboard = Dashboard::make('main', 'Main')->widgets([$widget]);
    $this->registry->register($dashboard);

    $controller = new WidgetDataController;
    $request = Request::create('/admin/dashboards/main/widgets/echo:totals/data', 'GET');

    $response = $controller->show($request, $this->registry, 'main', 'echo:totals');
    $payload = $response->getData(true);

    expect($payload)->toHaveKey('data');
    expect($payload['data'])->toBe(['filters' => []]);
});

it('passes request filters through to the widget before reading data()', function (): void {
    $widget = new EchoFiltersWidget('totals');
    $dashboard = Dashboard::make('main', 'Main')->widgets([$widget]);
    $this->registry->register($dashboard);

    $controller = new WidgetDataController;
    $request = Request::create(
        '/admin/dashboards/main/widgets/echo:totals/data',
        'GET',
        ['filters' => ['range' => '7d', 'team' => 'engineering']],
    );

    $response = $controller->show($request, $this->registry, 'main', 'echo:totals');
    $payload = $response->getData(true);

    expect($payload['data']['filters'])->toBe([
        'range' => '7d',
        'team' => 'engineering',
    ]);
});
