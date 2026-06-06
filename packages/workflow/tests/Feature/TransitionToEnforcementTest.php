<?php

declare(strict_types=1);

use Arqel\Workflow\Exceptions\IllegalTransitionException;
use Arqel\Workflow\Exceptions\UnauthorizedTransitionException;
use Arqel\Workflow\Tests\Fixtures\AuthorizedWorkflowOrder;
use Arqel\Workflow\Tests\Fixtures\CancelledState;
use Arqel\Workflow\Tests\Fixtures\DeniedWorkflowOrder;
use Arqel\Workflow\Tests\Fixtures\FreeFormWorkflowOrder;
use Arqel\Workflow\Tests\Fixtures\PaidState;
use Arqel\Workflow\Tests\Fixtures\PendingState;
use Arqel\Workflow\Tests\Fixtures\ShippedState;
use Arqel\Workflow\Tests\Fixtures\WorkflowOrder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/*
 * WF-006 server-side enforcement for the non-spatie fallback path of
 * `HasWorkflow::transitionTo()`. Before the fix this branch mutated + saved
 * with no eligibility (`from()`) check and never called the
 * `TransitionAuthorizer`, so illegal and denied transitions persisted.
 */

it('rejects a transition denied by the authorizer and does not persist', function (): void {
    $order = DeniedWorkflowOrder::create(['order_state' => PendingState::class]);

    expect(static fn () => $order->transitionTo(PaidState::class))
        ->toThrow(UnauthorizedTransitionException::class);

    expect($order->order_state)->toBe(PendingState::class)
        ->and($order->fresh()?->order_state)->toBe(PendingState::class);
});

it('rejects an ineligible transition with no declared path from the current state', function (): void {
    // WorkflowOrder declares Pending->Paid, Paid->Shipped, *->Cancelled.
    // There is no declared transition reaching Shipped from Pending.
    $order = WorkflowOrder::create(['order_state' => PendingState::class]);

    expect(static fn () => $order->transitionTo(ShippedState::class))
        ->toThrow(IllegalTransitionException::class);

    expect($order->order_state)->toBe(PendingState::class)
        ->and($order->fresh()?->order_state)->toBe(PendingState::class);
});

it('allows a declared, authorized transition and persists', function (): void {
    $order = AuthorizedWorkflowOrder::create(['order_state' => PendingState::class]);

    $order->transitionTo(PaidState::class);

    expect($order->order_state)->toBe(PaidState::class)
        ->and($order->fresh()?->order_state)->toBe(PaidState::class);
});

it('allows a declared transition authorized via a registered Gate', function (): void {
    Gate::define('transition-pending-to-paid', static fn (?Authenticatable $user): bool => true);

    // WorkflowOrder::PendingToPaid has no authorizeFor() — relies on the Gate.
    $order = WorkflowOrder::create(['order_state' => PendingState::class]);

    $order->transitionTo(PaidState::class);

    expect($order->fresh()?->order_state)->toBe(PaidState::class);
});

it('denies a declared transition with no authorizeFor and no Gate (deny-by-default)', function (): void {
    $order = WorkflowOrder::create(['order_state' => PendingState::class]);

    expect(static fn () => $order->transitionTo(PaidState::class))
        ->toThrow(UnauthorizedTransitionException::class);

    expect($order->fresh()?->order_state)->toBe(PendingState::class);
});

it('keeps the empty-transitions contract free-form (no enforcement)', function (): void {
    // Mirrors the showcase Ticket (transitions([])): any state is allowed.
    $order = FreeFormWorkflowOrder::create(['order_state' => PendingState::class]);

    $order->transitionTo(CancelledState::class);

    expect($order->fresh()?->order_state)->toBe(CancelledState::class);
});

it('honors an open (no-from) transition as a legal reachable path', function (): void {
    // AnyToCancelled has no from() -> always-available; authorize via Gate.
    Gate::define('transition-*-to-cancelled', static fn (?Authenticatable $user): bool => true);

    $order = WorkflowOrder::create(['order_state' => PendingState::class]);

    $order->transitionTo(CancelledState::class);

    expect($order->fresh()?->order_state)->toBe(CancelledState::class);
});
