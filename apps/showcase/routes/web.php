<?php

declare(strict_types=1);

use App\Http\Controllers\TicketTransitionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// The arqel-dev/workflow package ships a StateTransitionField + a
// Ticket::transitionTo() model method, but NO HTTP endpoint to trigger a
// transition from the UI. The showcase wires its own route + controller.
Route::post('/admin/tickets/{ticket}/transition', TicketTransitionController::class)
    ->middleware(['web', 'auth'])
    ->name('showcase.tickets.transition');
