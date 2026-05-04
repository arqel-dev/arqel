<?php

declare(strict_types=1);

use Arqel\Realtime\Http\Controllers\CollabDocumentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Arqel Realtime — Collaborative editing scaffold (RT-005)
|--------------------------------------------------------------------------
|
| GET  /admin/{resource}/{id}/collab/{field} — fetch Yjs snapshot.
| POST /admin/{resource}/{id}/collab/{field} — persist Yjs snapshot
|     com optimistic concurrency via coluna `version`.
|
| Sync delta-by-delta (real-time) deve correr por WebSocket/Reverb
| em pacote follow-up (`@arqel-dev/realtime` cliente). Aqui só temos
| snapshot persistence.
|
*/

Route::middleware(['web', 'auth'])->group(static function (): void {
    Route::get(
        '/admin/{resource}/{id}/collab/{field}',
        [CollabDocumentController::class, 'show'],
    )->name('arqel.realtime.collab.show');

    Route::post(
        '/admin/{resource}/{id}/collab/{field}',
        [CollabDocumentController::class, 'store'],
    )->name('arqel.realtime.collab.store');
});
