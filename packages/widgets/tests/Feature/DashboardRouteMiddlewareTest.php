<?php

declare(strict_types=1);

use Arqel\Core\Http\Middleware\HandleArqelInertiaRequests;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;

/*
 * The dashboard routes (/admin, /admin/dashboards/{id}) must carry the same
 * `HandleArqelInertiaRequests` middleware as the resource routes. That
 * middleware injects the shared Inertia props the admin shell depends on —
 * `panel.navigation` (the sidebar menu) and `i18n` (the locale switcher's
 * available locales + translations). Without it the dashboard renders with an
 * empty sidebar and a degraded language switcher.
 */

/**
 * @return array<int, string>
 */
function middlewareForRouteName(string $name): array
{
    /** @var IlluminateRoute|null $route */
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn (IlluminateRoute $r): bool => $r->getName() === $name);

    expect($route)->not->toBeNull("route [{$name}] is not registered");

    return $route->gatherMiddleware();
}

it('applies HandleArqelInertiaRequests to the main dashboard route', function (): void {
    expect(middlewareForRouteName('arqel.dashboard.main'))
        ->toContain(HandleArqelInertiaRequests::class);
});

it('applies HandleArqelInertiaRequests to the explicit dashboard route', function (): void {
    expect(middlewareForRouteName('arqel.dashboard.show'))
        ->toContain(HandleArqelInertiaRequests::class);
});
