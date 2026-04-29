<?php

declare(strict_types=1);

use Arqel\Widgets\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/admin', [DashboardController::class, 'show'])
        ->name('arqel.dashboard.main');

    Route::get('/admin/dashboards/{dashboardId}', [DashboardController::class, 'show'])
        ->name('arqel.dashboard.show');
});
