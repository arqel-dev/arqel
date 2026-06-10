<?php

declare(strict_types=1);

use Arqel\Workflow\Exceptions\UnauthorizedTransitionException;
use Arqel\Workflow\Tests\Fixtures\PaidState;
use Arqel\Workflow\Tests\Fixtures\ShippedState;
use Arqel\Workflow\Tests\Fixtures\SpatieStyleState;
use Arqel\Workflow\Tests\Fixtures\SpatieWorkflowOrder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/*
 * #242 — server-side authorization for the SPATIE path of
 * `HasWorkflow::transitionTo()`.
 *
 * Before the fix, when the state value is an object owning its own
 * `transitionTo()` (the spatie/laravel-model-states API) the trait delegated
 * straight to spatie and NEVER consulted the `TransitionAuthorizer` /
 * `transition-*-to-*` gate. A user could therefore drive a state change that
 * bypassed the deny-by-default authorization the package advertises.
 *
 * The ability slug for SpatieStyleState -> PaidState resolves to
 * `transition-spatie-style-to-paid`.
 */

beforeEach(function (): void {
    SpatieStyleState::reset();
});

it('blocks an unauthorized transition on the spatie path (deny-by-default)', function (): void {
    // No Gate defined => deny-by-default must block, throwing BEFORE spatie's
    // own transitionTo() body runs. Pre-fix this DID NOT throw (RED).
    $order = SpatieWorkflowOrder::create(['order_state' => SpatieStyleState::class]);

    expect(static fn () => $order->transitionTo(PaidState::class))
        ->toThrow(UnauthorizedTransitionException::class);

    // spatie's mutation must never have been reached.
    expect(SpatieStyleState::$transitions)->toBe([]);
});

it('blocks a transition the gate explicitly denies on the spatie path', function (): void {
    Gate::define('transition-spatie-style-to-paid', static fn (?Authenticatable $user): bool => false);

    $order = SpatieWorkflowOrder::create(['order_state' => SpatieStyleState::class]);

    expect(static fn () => $order->transitionTo(PaidState::class))
        ->toThrow(UnauthorizedTransitionException::class);

    expect(SpatieStyleState::$transitions)->toBe([]);
});

it('allows an authorized transition on the spatie path and delegates to spatie', function (): void {
    Gate::define('transition-spatie-style-to-paid', static fn (?Authenticatable $user): bool => true);

    $order = SpatieWorkflowOrder::create(['order_state' => SpatieStyleState::class]);

    $order->transitionTo(PaidState::class);

    // Authorization passed => spatie's own transitionTo() body was reached.
    expect(SpatieStyleState::$transitions)->toBe([PaidState::class]);
});

it('does not impose the Arqel graph on the spatie path (spatie owns reachability)', function (): void {
    // Authorize a target the Arqel definition does NOT enumerate as a declared
    // transition. The full assertTransitionAllowed() would reject it as illegal;
    // the spatie path must NOT — it only runs authorization, letting spatie own
    // the graph.
    Gate::define('transition-spatie-style-to-shipped', static fn (?Authenticatable $user): bool => true);

    $order = SpatieWorkflowOrder::create(['order_state' => SpatieStyleState::class]);

    $order->transitionTo(ShippedState::class);

    expect(SpatieStyleState::$transitions)->toBe([ShippedState::class]);
});
