<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\CommandPaletteController;
use Arqel\Core\Http\Controllers\LocaleController;
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
});

// Locale switcher endpoint (i18n). Lives outside the `auth` group
// so guest pages (login/register) também podem mudar de idioma.
Route::middleware(['web'])->group(function (): void {
    Route::post('/admin/locale', LocaleController::class)
        ->name('arqel.locale.update');
});
