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
// (e.g. /admin/login, /admin/logout from arqel-dev/auth) must not be
// captured by the polymorphic `{resource}` slug.
//
// Use lookahead with `(?:/|$)` so the exclusion fires even when the
// reserved slug appears as the first segment of a multi-segment route
// (e.g. `/admin/reset-password/{token}` or `/admin/email/verify/{id}/{hash}`).
// Anchoring with `$` alone only catches single-segment URLs because the
// captured `{resource}` value never includes a slash; multi-segment URLs
// end the captured group before the `$` anchor would trigger.
$reservedSlugs = '(?!(?:login|logout|register|forgot-password|reset-password|email|dashboards)(?:/|$))[^/]+';

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

    // Bulk action dispatch (BUG-VAL-010). The React side POSTs the
    // selected `record_ids` to this endpoint; the controller resolves
    // the BulkAction by name on the resource's table and executes it.
    Route::post('{resource}/bulk/{action}', [ResourceController::class, 'bulkAction'])
        ->name('bulk')
        ->where('resource', $reservedSlugs)
        ->where('action', '[a-z][a-z0-9_-]*');
});
