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

// Reserved sub-paths that Arqel itself owns under the panel prefix
// (e.g. /admin/login, /admin/logout from arqel/auth) must not be
// captured by the polymorphic `{resource}` slug.
$reservedSlugs = '(?!(?:login|logout|register|forgot-password|reset-password|email)(?:/|$))[^/]+';

Route::name('arqel.resources.')->group(function () use ($reservedSlugs): void {
    Route::get('{resource}', [ResourceController::class, 'index'])
        ->name('index')
        ->where('resource', $reservedSlugs);
    Route::get('{resource}/create', [ResourceController::class, 'create'])
        ->name('create')
        ->where('resource', $reservedSlugs);

    // Precognition support (RF-FM-10) — Laravel runs validation only,
    // returning 204 No Content for `Precognition: true` requests.
    Route::middleware('precognitive')->post('{resource}', [ResourceController::class, 'store'])
        ->name('store')
        ->where('resource', $reservedSlugs);

    Route::get('{resource}/{id}', [ResourceController::class, 'show'])
        ->name('show')
        ->where('resource', $reservedSlugs)
        ->where('id', '[0-9a-zA-Z\-_]+');
    Route::get('{resource}/{id}/edit', [ResourceController::class, 'edit'])
        ->name('edit')
        ->where('resource', $reservedSlugs)
        ->where('id', '[0-9a-zA-Z\-_]+');
    Route::middleware('precognitive')->put('{resource}/{id}', [ResourceController::class, 'update'])
        ->name('update')
        ->where('resource', $reservedSlugs)
        ->where('id', '[0-9a-zA-Z\-_]+');
    Route::middleware('precognitive')->patch('{resource}/{id}', [ResourceController::class, 'update'])
        ->where('resource', $reservedSlugs)
        ->where('id', '[0-9a-zA-Z\-_]+');
    Route::delete('{resource}/{id}', [ResourceController::class, 'destroy'])
        ->name('destroy')
        ->where('resource', $reservedSlugs)
        ->where('id', '[0-9a-zA-Z\-_]+');
});
