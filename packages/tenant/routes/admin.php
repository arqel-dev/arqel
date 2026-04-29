<?php

declare(strict_types=1);

use Arqel\Tenant\Http\Controllers\TenantSwitcherController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('admin/tenants')->group(function (): void {
    Route::post('{tenantId}/switch', [TenantSwitcherController::class, 'switch'])
        ->name('arqel.tenant.switch');

    Route::get('available', [TenantSwitcherController::class, 'list'])
        ->name('arqel.tenant.available');
});
