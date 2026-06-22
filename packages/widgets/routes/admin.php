<?php

declare(strict_types=1);

use Arqel\Core\Http\Middleware\HandleArqelInertiaRequests;
use Arqel\Widgets\Http\Controllers\DashboardController;
use Arqel\Widgets\Http\Controllers\WidgetDataController;
use Illuminate\Support\Facades\Route;

// `HandleArqelInertiaRequests` injects the shared Inertia props the admin shell
// depends on — `panel.navigation` (the sidebar menu) and `i18n` (the locale
// switcher's available locales + translations) — mirroring the resource routes
// registered by ArqelServiceProvider. Without it the dashboard renders with an
// empty sidebar and a degraded language switcher.
Route::middleware(['web', 'auth', HandleArqelInertiaRequests::class])->group(function (): void {
    Route::get('/admin', [DashboardController::class, 'show'])
        ->name('arqel.dashboard.main');

    Route::get('/admin/dashboards/{dashboardId}', [DashboardController::class, 'show'])
        ->name('arqel.dashboard.show');

    Route::get('/admin/dashboards/{dashboardId}/widgets/{widgetId}/data', [WidgetDataController::class, 'show'])
        ->name('arqel.dashboard.widget-data');
});
