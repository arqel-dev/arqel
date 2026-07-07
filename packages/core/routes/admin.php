<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\CommandPaletteController;
use Arqel\Core\Http\Controllers\LocaleController;
use Arqel\Core\Http\Controllers\NotificationController;
use Arqel\Core\Http\Middleware\HandleArqelInertiaRequests;
use Illuminate\Support\Facades\Route;

/*
 * Arqel core admin routes.
 *
 * Loaded via `hasRoute('admin')` in ArqelServiceProvider. These
 * are the framework-level endpoints that do NOT depend on a
 * specific Panel — Resource routes still go through
 * `registerResourceRoutes()` because they need the panel prefix
 * + middleware stack.
 */

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/admin/commands', CommandPaletteController::class)
        ->name('arqel.commands');

    Route::get('/admin/notifications', [NotificationController::class, 'index'])
        ->middleware(HandleArqelInertiaRequests::class)
        ->name('arqel.notifications.index');
    Route::post('/admin/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('arqel.notifications.read-all');
    Route::post('/admin/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->name('arqel.notifications.read');
    Route::delete('/admin/notifications/{notification}', [NotificationController::class, 'destroy'])
        ->name('arqel.notifications.destroy');
});

// Locale switcher endpoint (i18n). Lives outside the `auth` group
// so guest pages (login/register) também podem mudar de idioma.
Route::middleware(['web'])->group(function (): void {
    Route::post('/admin/locale', LocaleController::class)
        ->name('arqel.locale.update');
});
