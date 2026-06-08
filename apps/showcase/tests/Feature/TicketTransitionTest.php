<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TicketTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transitions_a_ticket_via_the_custom_showcase_route(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['status' => 'open']);

        $this->actingAs($user)
            ->post("/admin/tickets/{$ticket->id}/transition", ['to' => 'in_progress'])
            ->assertRedirect();

        $this->assertSame('in_progress', $ticket->fresh()->status);
    }

    public function test_rejects_a_transition_to_an_unknown_state(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['status' => 'open']);

        $this->actingAs($user)
            ->post("/admin/tickets/{$ticket->id}/transition", ['to' => 'garbage'])
            ->assertSessionHasErrors('to');

        // The allow-list rejected the payload before mutating the model.
        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_a_guest_cannot_transition_a_ticket(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'open']);

        $response = $this->post(
            "/admin/tickets/{$ticket->id}/transition",
            ['to' => 'in_progress'],
        );

        // The `auth` middleware stops the guest before the controller runs, so
        // the request never succeeds and the ticket is untouched. (This headless
        // showcase has no `login` route, so the middleware's guest redirect is
        // not asserted — only that the transition did NOT happen.)
        $this->assertFalse($response->isSuccessful());
        $this->assertSame('open', $ticket->fresh()->status);
    }
}
