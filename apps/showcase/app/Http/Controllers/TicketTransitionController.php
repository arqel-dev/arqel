<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ticket;
use Arqel\Workflow\Fields\StateTransitionField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Executes a workflow transition for a {@see Ticket}.
 *
 * The framework's `arqel-dev/workflow` package ships the
 * {@see StateTransitionField} (renders the UI) and
 * `Ticket::transitionTo()` (mutates + audits), but provides NO HTTP endpoint
 * to wire them together. Every consuming app must hand-roll a route +
 * controller — this is that wiring for the showcase.
 */
final class TicketTransitionController extends Controller
{
    public function __invoke(Request $request, Ticket $ticket): RedirectResponse
    {
        $to = (string) $request->input('to');

        $ticket->transitionTo($to);

        return back();
    }
}
