<?php

declare(strict_types=1);

use App\Http\Controllers\TicketTransitionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// NOTE: the versions-demo surface lives in AppServiceProvider::boot(), not
// here. Arqel's panel registers a greedy `admin/{resource}` route in an
// `app->booted()` callback that shadows single-segment `/admin/*` routes
// declared in this file, so the route must be registered earlier (provider
// boot) to win. See AppServiceProvider + the Phase-5 findings ledger.

// The arqel-dev/workflow package ships a StateTransitionField + a
// Ticket::transitionTo() model method, but NO HTTP endpoint to trigger a
// transition from the UI. The showcase wires its own route + controller.
Route::post('/admin/tickets/{ticket}/transition', TicketTransitionController::class)
    ->middleware(['web', 'auth'])
    ->name('showcase.tickets.transition');
