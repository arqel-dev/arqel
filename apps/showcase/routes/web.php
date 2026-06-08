<?php

declare(strict_types=1);

use App\Http\Controllers\TicketTransitionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/admin');

// Demo surface that mounts VersionHistoryDrawer (VersionsDemo.tsx) so the
// Phase-5 versioning E2E can reach @arqel-dev/versioning's timeline/diff
// components. Behind web+auth like the rest of the panel.
Route::get('/admin/versions-demo', static fn () => Inertia::render('VersionsDemo'))
    ->middleware(['web', 'auth'])
    ->name('showcase.versions-demo');

// The arqel-dev/workflow package ships a StateTransitionField + a
// Ticket::transitionTo() model method, but NO HTTP endpoint to trigger a
// transition from the UI. The showcase wires its own route + controller.
Route::post('/admin/tickets/{ticket}/transition', TicketTransitionController::class)
    ->middleware(['web', 'auth'])
    ->name('showcase.tickets.transition');
