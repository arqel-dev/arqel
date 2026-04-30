<?php

declare(strict_types=1);

use Arqel\Workflow\Events\StateTransitioned;
use Arqel\Workflow\Tests\Fixtures\PaidState;
use Arqel\Workflow\Tests\Fixtures\PendingState;
use Arqel\Workflow\Tests\Fixtures\SampleSpatieTransition;
use Arqel\Workflow\Tests\Fixtures\WorkflowOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

it('dispatches StateTransitioned when transitionTo is called', function (): void {
    Event::fake([StateTransitioned::class]);

    $order = WorkflowOrder::create(['order_state' => PendingState::class]);
    $order->transitionTo(PaidState::class);

    Event::assertDispatched(
        StateTransitioned::class,
        fn (StateTransitioned $event): bool => $event->record->is($order)
            && $event->from === PendingState::class
            && $event->to === PaidState::class,
    );
});

it('propagates the context array to the event', function (): void {
    Event::fake([StateTransitioned::class]);

    $order = WorkflowOrder::create(['order_state' => PendingState::class]);
    $order->transitionTo(PaidState::class, ['reason' => 'webhook']);

    Event::assertDispatched(
        StateTransitioned::class,
        fn (StateTransitioned $event): bool => $event->context === ['reason' => 'webhook'],
    );
});

it('captures the authenticated user id when available', function (): void {
    Event::fake([StateTransitioned::class]);

    Auth::shouldReceive('id')->andReturn(7);

    $order = WorkflowOrder::create(['order_state' => PendingState::class]);
    $order->transitionTo(PaidState::class);

    Event::assertDispatched(
        StateTransitioned::class,
        fn (StateTransitioned $event): bool => $event->userId === 7,
    );
});

it('falls back to null userId when no auth is bound', function (): void {
    Event::fake([StateTransitioned::class]);

    $order = WorkflowOrder::create(['order_state' => PendingState::class]);
    $order->transitionTo(PaidState::class);

    Event::assertDispatched(
        StateTransitioned::class,
        fn (StateTransitioned $event): bool => $event->userId === null,
    );
});

it('skips the event when audit is disabled', function (): void {
    config()->set('arqel-workflow.audit.enabled', false);

    Event::fake([StateTransitioned::class]);

    $order = WorkflowOrder::create(['order_state' => PendingState::class]);
    $order->transitionTo(PaidState::class);

    Event::assertNotDispatched(StateTransitioned::class);
});

it('skips the event when log_via is not "event"', function (): void {
    config()->set('arqel-workflow.audit.log_via', 'silent');

    Event::fake([StateTransitioned::class]);

    $order = WorkflowOrder::create(['order_state' => PendingState::class]);
    $order->transitionTo(PaidState::class);

    Event::assertNotDispatched(StateTransitioned::class);
});

it('persists the new state on the model after transitionTo', function (): void {
    $order = WorkflowOrder::create(['order_state' => PendingState::class]);

    $order->transitionTo(PaidState::class);

    expect($order->fresh()?->order_state)->toBe(PaidState::class);
});

it('lets a spatie-style Transition class emit StateTransitioned via the trait', function (): void {
    Event::fake([StateTransitioned::class]);

    $order = WorkflowOrder::create(['order_state' => PendingState::class]);

    (new SampleSpatieTransition)->fire(
        $order,
        PendingState::class,
        PaidState::class,
        ['source' => 'spatie-transition'],
    );

    Event::assertDispatched(
        StateTransitioned::class,
        fn (StateTransitioned $event): bool => $event->from === PendingState::class
            && $event->to === PaidState::class
            && $event->context === ['source' => 'spatie-transition'],
    );
});
