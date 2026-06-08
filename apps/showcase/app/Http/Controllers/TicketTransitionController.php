<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ticket;
use Arqel\Workflow\Fields\StateTransitionField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
        // Authorize via the auto-discovered TicketPolicy::transition() gate.
        // The `auth` middleware already guarantees an authenticated user; this
        // turns the route from an open IDOR into a policy-gated endpoint that a
        // real app would tighten with ownership/role checks.
        Gate::authorize('transition', $ticket);

        // Allow-list the target state against the workflow's real states so a
        // crafted payload cannot drive the ticket into an unknown status.
        $validated = $request->validate([
            'to' => ['required', 'string', Rule::in(['open', 'in_progress', 'resolved'])],
        ]);

        $ticket->transitionTo($validated['to']);

        return back();
    }
}
