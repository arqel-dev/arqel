<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\ResourceController;
use Illuminate\Support\Facades\Route;

/*
 * Arqel resource routes.
 *
 * Mounted by `ArqelServiceProvider` under each registered Panel's
 * path + middleware. The `{resource}` parameter is a slug routed
 * polymorphically through `ResourceController` — we deliberately
 * avoid `Route::resource()` because the controller is generic.
 */

Route::name('arqel.resources.')->group(function (): void {
    Route::get('{resource}', [ResourceController::class, 'index'])->name('index');
    Route::get('{resource}/create', [ResourceController::class, 'create'])->name('create');
    Route::post('{resource}', [ResourceController::class, 'store'])->name('store');
    Route::get('{resource}/{id}', [ResourceController::class, 'show'])
        ->name('show')
        ->where('id', '[0-9a-zA-Z\-_]+');
    Route::get('{resource}/{id}/edit', [ResourceController::class, 'edit'])
        ->name('edit')
        ->where('id', '[0-9a-zA-Z\-_]+');
    Route::put('{resource}/{id}', [ResourceController::class, 'update'])
        ->name('update')
        ->where('id', '[0-9a-zA-Z\-_]+');
    Route::patch('{resource}/{id}', [ResourceController::class, 'update'])
        ->where('id', '[0-9a-zA-Z\-_]+');
    Route::delete('{resource}/{id}', [ResourceController::class, 'destroy'])
        ->name('destroy')
        ->where('id', '[0-9a-zA-Z\-_]+');
});
