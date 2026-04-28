<?php

declare(strict_types=1);

use Arqel\Actions\Http\Controllers\ActionController;
use Illuminate\Support\Facades\Route;

/*
 * Arqel action invocation routes.
 *
 * Mounted by `ActionsServiceProvider` under the same panel path
 * + middleware as the resource routes. The `{resource}` parameter
 * is the slug; specific actions are resolved by name on the
 * Resource's matching collection.
 */

Route::name('arqel.actions.')->group(function (): void {
    Route::post('{resource}/bulk-actions/{action}', [ActionController::class, 'invokeBulk'])
        ->name('bulk');

    Route::post('{resource}/actions/{action}', [ActionController::class, 'invokeToolbar'])
        ->name('toolbar');

    Route::post('{resource}/{id}/actions/{action}', [ActionController::class, 'invokeRow'])
        ->name('row')
        ->where('id', '[0-9a-zA-Z\-_]+');

    Route::post('{resource}/{id}/header-actions/{action}', [ActionController::class, 'invokeHeader'])
        ->name('header')
        ->where('id', '[0-9a-zA-Z\-_]+');
});
