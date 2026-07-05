<?php

declare(strict_types=1);

use Arqel\Import\Http\Controllers\FailedRowsDownloadController;
use Arqel\Import\Http\Controllers\ImportUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('admin/imports', ImportUploadController::class)->name('arqel.imports.upload');
    Route::get('admin/imports/{importId}/failed-rows', FailedRowsDownloadController::class)
        ->where('importId', '[a-f0-9-]+')
        ->name('arqel.imports.failed-rows');
});
