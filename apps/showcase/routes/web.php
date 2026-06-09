<?php

declare(strict_types=1);

use App\Http\Controllers\TicketTransitionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// NOTE: the versions-demo surface lives in AppServiceProvider::register(),
// not here. Arqel's core provider registers a greedy `admin/{resource}` route
// directly in ArqelServiceProvider::packageBooted() (its boot phase), which
// shadows single-segment `/admin/*` routes declared in this file, so the
// route must be registered earlier — in this app's register() — to win the
// match. See AppServiceProvider + the Phase-5 findings ledger (CANDIDATE #7C).

// The arqel-dev/workflow package ships a StateTransitionField + a
// Ticket::transitionTo() model method, but NO HTTP endpoint to trigger a
// transition from the UI. The showcase wires its own route + controller.
Route::post('/admin/tickets/{ticket}/transition', TicketTransitionController::class)
    ->middleware(['web', 'auth'])
    ->name('showcase.tickets.transition');
