<?php

declare(strict_types=1);

use Arqel\Workflow\Tests\Fixtures\AnyToCancelled;
use Arqel\Workflow\Tests\Fixtures\PaidState;
use Arqel\Workflow\Tests\Fixtures\PaidToShipped;
use Arqel\Workflow\Tests\Fixtures\PendingState;
use Arqel\Workflow\Tests\Fixtures\PendingToPaid;
use Arqel\Workflow\Tests\Fixtures\WorkflowOrder;

it('returns metadata for the current state', function (): void {
    $order = new WorkflowOrder(['order_state' => PendingState::class]);

    expect($order->getCurrentStateMetadata())->toBe([
        'label' => 'Pending',
        'color' => 'warning',
        'icon' => 'clock',
    ]);
});

it('returns null when the current state is missing', function (): void {
    $order = new WorkflowOrder;

    expect($order->getCurrentStateMetadata())->toBeNull();
});

it('returns null when the state value is not registered', function (): void {
    $order = new WorkflowOrder(['order_state' => 'App\\Unknown\\State']);

    expect($order->getCurrentStateMetadata())->toBeNull();
});

it('lists transitions whose from() matches the current state, plus open ones', function (): void {
    $order = new WorkflowOrder(['order_state' => PendingState::class]);

    expect($order->getAvailableTransitions())
        ->toBe([PendingToPaid::class, AnyToCancelled::class]);
});

it('filters transitions when the current state changes', function (): void {
    $order = new WorkflowOrder(['order_state' => PaidState::class]);

    expect($order->getAvailableTransitions())
        ->toBe([PaidToShipped::class, AnyToCancelled::class]);
});

it('still surfaces open transitions when the state field is null', function (): void {
    $order = new WorkflowOrder;

    expect($order->getAvailableTransitions())
        ->toBe([AnyToCancelled::class]);
});
