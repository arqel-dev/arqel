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

// Constrain the polymorphic `{resource}` wildcard to the actually-
// registered Resource slugs (#234). Anything else under the panel prefix
// — auth sub-paths (`/admin/login`, `/admin/reset-password/{token}` from
// arqel-dev/auth, which are *separate* routes), app-defined routes such as
// `/admin/versions-demo`, etc. — then falls through to its own route
// instead of being captured by this wildcard and 404-ed.
//
// The slug list is published on the container by
// `ArqelServiceProvider::registerResourceRoutes()` right before this file
// is loaded, so it always reflects the post-sync registry.
$registeredSlugs = app()->bound('arqel.resource-route-slugs')
    ? app('arqel.resource-route-slugs')
    : [];
$registeredSlugs = is_array($registeredSlugs) ? array_values(array_filter($registeredSlugs, 'is_string')) : [];

// When no resources are registered the wildcard must match *nothing* so it
// never shadows app routes. `(*FAIL)` is a PCRE control verb that always
// fails the match; otherwise build an anchored alternation of quoted slugs.
$resourceSlugPattern = $registeredSlugs === []
    ? '(*FAIL)'
    : '(?:'.implode('|', array_map(
        static fn (string $slug): string => preg_quote($slug, '/'),
        $registeredSlugs,
    )).')';

Route::name('arqel.resources.')->group(function () use ($resourceSlugPattern): void {
    Route::get('{resource}', [ResourceController::class, 'index'])
        ->name('index')
        ->where('resource', $resourceSlugPattern);
    Route::get('{resource}/create', [ResourceController::class, 'create'])
        ->name('create')
        ->where('resource', $resourceSlugPattern);

    // Precognition support (RF-FM-10) — Laravel runs validation only,
    // returning 204 No Content for `Precognition: true` requests.
    Route::middleware('precognitive')->post('{resource}', [ResourceController::class, 'store'])
        ->name('store')
        ->where('resource', $resourceSlugPattern);

    Route::get('{resource}/{id}', [ResourceController::class, 'show'])
        ->name('show')
        ->where('resource', $resourceSlugPattern)
        ->where('id', '[0-9a-zA-Z\-_]+');
    Route::get('{resource}/{id}/edit', [ResourceController::class, 'edit'])
        ->name('edit')
        ->where('resource', $resourceSlugPattern)
        ->where('id', '[0-9a-zA-Z\-_]+');
    Route::middleware('precognitive')->put('{resource}/{id}', [ResourceController::class, 'update'])
        ->name('update')
        ->where('resource', $resourceSlugPattern)
        ->where('id', '[0-9a-zA-Z\-_]+');
    Route::middleware('precognitive')->patch('{resource}/{id}', [ResourceController::class, 'update'])
        ->where('resource', $resourceSlugPattern)
        ->where('id', '[0-9a-zA-Z\-_]+');
    Route::delete('{resource}/{id}', [ResourceController::class, 'destroy'])
        ->name('destroy')
        ->where('resource', $resourceSlugPattern)
        ->where('id', '[0-9a-zA-Z\-_]+');

    // Bulk action dispatch (BUG-VAL-010). The React side POSTs the
    // selected `record_ids` to this endpoint; the controller resolves
    // the BulkAction by name on the resource's table and executes it.
    Route::post('{resource}/bulk/{action}', [ResourceController::class, 'bulkAction'])
        ->name('bulk')
        ->where('resource', $resourceSlugPattern)
        ->where('action', '[a-z][a-z0-9_-]*');

    // Custom row/header/toolbar action dispatch (#231). A custom action
    // with a server-side `->action(Closure)` (and no explicit `->url()`)
    // funnels through this authorised endpoint instead of the dead
    // standalone `arqel.actions.*` routes removed in #174. The optional
    // `{id}` segment carries the target record for row/header actions;
    // toolbar actions omit it. `ResourceController::rowAction` resolves
    // the action by name on the resource's `actions`/`headerActions`/
    // `toolbarActions` collection (duck-typed), authorises it (resource
    // Gate + the action's `canBeExecutedBy`), validates the form payload
    // and runs `execute()`. It rides the same panel/config middleware
    // stack (web + auth + tenant) as every other resource route.
    Route::post('{resource}/actions/{action}/{id?}', [ResourceController::class, 'rowAction'])
        ->name('action')
        ->where('resource', $resourceSlugPattern)
        ->where('action', '[a-z][a-z0-9_-]*')
        ->where('id', '[0-9a-zA-Z\-_]+');
});
