<?php

declare(strict_types=1);

use Arqel\Export\Http\Controllers\ExportDownloadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/admin/exports/{exportId}/download', [ExportDownloadController::class, 'download'])
        ->name('arqel.export.download')
        ->where('exportId', '[a-f0-9-]+');
});
