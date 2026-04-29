<?php

declare(strict_types=1);

use Arqel\Audit\Http\Controllers\RecordActivityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/admin/audit/{subjectType}/{subjectId}/activity', [RecordActivityController::class, 'show'])
        ->name('arqel.audit.record-activity')
        ->where('subjectType', '.*');
});
