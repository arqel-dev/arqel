<?php

declare(strict_types=1);

use Arqel\Fields\Http\Controllers\FieldSearchController;
use Arqel\Fields\Http\Controllers\FieldUploadController;
use Illuminate\Support\Facades\Route;

/*
 * Arqel field-side endpoints.
 *
 * Mounted by `FieldServiceProvider` under the same panel path +
 * middleware as the resource routes. The `{resource}/{field}`
 * pair identifies the target field on the active Resource.
 *
 * `BelongsToField` consumes the search route; `FileField` /
 * `ImageField` consume the upload pair.
 */

Route::name('arqel.fields.')->group(function (): void {
    Route::get('{resource}/fields/{field}/search', FieldSearchController::class)
        ->name('search')
        ->middleware('throttle:30,1');

    Route::post('{resource}/fields/{field}/upload', [FieldUploadController::class, 'store'])
        ->name('upload.store');

    Route::delete('{resource}/fields/{field}/upload', [FieldUploadController::class, 'destroy'])
        ->name('upload.destroy');
});
