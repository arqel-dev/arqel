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
}
