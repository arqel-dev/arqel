<?php

declare(strict_types=1);

use Arqel\Workflow\Fields\StateTransitionField;
use Arqel\Workflow\Models\StateTransition;
use Arqel\Workflow\Tests\Fixtures\CancelledState;
use Arqel\Workflow\Tests\Fixtures\PaidState;
use Arqel\Workflow\Tests\Fixtures\PendingState;
use Arqel\Workflow\Tests\Fixtures\ShippedState;
use Arqel\Workflow\Tests\Fixtures\WorkflowOrder;
use Illuminate\Support\Facades\Auth;

it('persists a StateTransition row when transitionTo is called', function (): void {
    Auth::shouldReceive('id')->andReturn(42);

    $order = WorkflowOrder::create(['order_state' => PendingState::class]);
    $order->transitionTo(PaidState::class, ['reason' => 'webhook']);

    $entry = StateTransition::query()->first();
    expect($entry)->not->toBeNull();
    assert($entry instanceof StateTransition);

    expect($entry->model_type)->toBe(WorkflowOrder::class)
        ->and((int) $entry->model_id)->toBe($order->getKey())
        ->and($entry->from_state)->toBe(PendingState::class)
        ->and($entry->to_state)->toBe(PaidState::class)
        ->and($entry->transitioned_by_user_id)->toBe(42)
        ->and($entry->metadata)->toBe(['reason' => 'webhook']);
});

it('persists context as JSON metadata array', function (): void {
    $order = WorkflowOrder::create(['order_state' => PendingState::class]);
    $order->transitionTo(PaidState::class, ['actor' => 'cli', 'attempt' => 3]);

    $entry = StateTransition::query()->first();

    expect($entry?->metadata)->toBe(['actor' => 'cli', 'attempt' => 3]);
});

it('exposes history via the HasWorkflow stateTransitions relationship', function (): void {
    $order = WorkflowOrder::create(['order_state' => PendingState::class]);
    $order->transitionTo(PaidState::class);

    $transitions = $order->stateTransitions()->get();

    expect($transitions)->toHaveCount(1)
        ->and($transitions->first()?->to_state)->toBe(PaidState::class);
});

it('does not persist when history is disabled', function (): void {
    config()->set('arqel-workflow.history.enabled', false);

    // Re-bootstrap listener registration scenario: with the flag off,
    // even though listener is already registered for the test app
    // (provider booted earlier), the listener itself short-circuits.
    $order = WorkflowOrder::create(['order_state' => PendingState::class]);
    $order->transitionTo(PaidState::class);

    expect(StateTransition::query()->count())->toBe(0);
});

it('records two sequential transitions in descending order', function (): void {
    $order = WorkflowOrder::create(['order_state' => PendingState::class]);

    $order->transitionTo(PaidState::class);
    $order->transitionTo(ShippedState::class);

    $rows = $order->stateTransitions()->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->first()?->to_state)->toBe(ShippedState::class)
        ->and($rows->last()?->to_state)->toBe(PaidState::class);
});

it('returns non-empty history payload from StateTransitionField::resolveHistory', function (): void {
    $order = WorkflowOrder::create(['order_state' => PendingState::class]);
    $order->transitionTo(PaidState::class);
    $order->transitionTo(CancelledState::class, ['reason' => 'fraud']);

    $field = StateTransitionField::make('state')->record($order);

    $history = $field->resolveHistory();

    expect($history)->toHaveCount(2)
        ->and($history[0]['to'])->toBe(CancelledState::class)
        ->and($history[0]['from'])->toBe(PaidState::class)
        ->and($history[0]['metadata'])->toBe(['reason' => 'fraud'])
        ->and($history[1]['to'])->toBe(PaidState::class)
        ->and($history[1]['from'])->toBe(PendingState::class);
});

it('returns empty history when no record is bound to the field', function (): void {
    $field = StateTransitionField::make('state');

    expect($field->resolveHistory())->toBe([]);
});
