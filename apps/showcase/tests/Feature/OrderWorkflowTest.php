<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * TDD coverage for the showcase Order model (Task 2.1): a 5-state guarded
 * workflow (pending → paid → shipped → delivered, plus cancelled) backed by
 * Arqel\Workflow, with soft-deletes.
 *
 * The showcase app runs PHPUnit (not Pest), so these are class-based tests
 * mirroring the surrounding `ShowcaseSmokeTest` convention. The assertions
 * are identical to the planned Pest spec.
 */
final class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'orders@arqel.test'],
            ['name' => 'Orders Admin', 'password' => Hash::make('password')],
        );
    }

    public function test_it_declares_a_five_state_order_workflow_with_guarded_transitions(): void
    {
        $order = Order::factory()->create(['state' => 'pending']);

        $this->assertSame('state', $order->arqelWorkflow()->getField());
        $this->assertEqualsCanonicalizing(
            ['pending', 'paid', 'shipped', 'delivered', 'cancelled'],
            array_keys($order->arqelWorkflow()->getStates()),
        );

        // The pending → paid transition is guarded (authorizeFor requires an
        // authenticated user), so act as one before transitioning.
        $this->actingAs($this->makeUser());

        $order->transitionTo('paid');
        $this->assertSame('paid', $order->fresh()?->state);
    }

    public function test_it_soft_deletes_and_restores_an_order(): void
    {
        $order = Order::factory()->create();

        $order->delete();
        $this->assertSame(0, Order::count());
        $this->assertSame(1, Order::withTrashed()->count());

        $order->restore();
        $this->assertSame(1, Order::count());
    }
}
