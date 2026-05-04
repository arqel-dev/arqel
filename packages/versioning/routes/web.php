<?php

declare(strict_types=1);

use Arqel\Versioning\Http\Controllers\VersionHistoryController;
use Arqel\Versioning\Http\Controllers\VersionRestoreController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes do pacote `arqel-dev/versioning`
|--------------------------------------------------------------------------
|
| Endpoints HTTP expostos pelo pacote. Carregados automaticamente pelo
| `VersioningServiceProvider`. O middleware `web` + `auth` é aplicado
| no nível da rota — ajustar via app no caso de bearer-only / SPA.
*/

Route::get('/admin/{resource}/{id}/versions', VersionHistoryController::class)
    ->middleware(['web', 'auth'])
    ->name('arqel.versioning.history');

Route::post(
    '/admin/{resource}/{id}/versions/{versionId}/restore',
    VersionRestoreController::class,
)
    ->middleware(['web', 'auth'])
    ->name('arqel.versioning.restore');
