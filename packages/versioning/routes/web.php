<?php

declare(strict_types=1);

use Arqel\Versioning\Http\Controllers\VersionHistoryController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/{resource}/{id}/versions', VersionHistoryController::class)
    ->middleware(['web', 'auth'])
    ->name('arqel.versioning.history');
