<?php

declare(strict_types=1);

use Arqel\Workflow\Fields\StateTransitionField;
use Arqel\Workflow\Models\StateTransition;
use Arqel\Workflow\Tests\Fixtures\CancelledState;
use Arqel\Workflow\Tests\Fixtures\PaidState;
use Arqel\Workflow\Tests\Fixtures\PendingState;
use Arqel\Workflow\Tests\Fixtures\ShippedState;
use Arqel\Workflow\Tests\Fixtures\WorkflowOrder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

// `WorkflowOrder`'s declared transitions carry no `authorizeFor()`, so the
// deny-by-default `TransitionAuthorizer` (WF-006) would block them. These
// tests exercise the audit history, not the policy — grant the documented
// Gate abilities so the transitions are legal + authorized.
beforeEach(function (): void {
    Gate::define('transition-pending-to-paid', static fn (?Authenticatable $user): bool => true);
    Gate::define('transition-paid-to-shipped', static fn (?Authenticatable $user): bool => true);
    Gate::define('transition-*-to-cancelled', static fn (?Authenticatable $user): bool => true);
});

// `enforceMorphMap` mutates a process-wide static registry on the Relation
// class AND sets the `requireMorphMap` flag. Reset both after every test so the
// morph-map case below does not leak into the other tests (which assume the
// no-morph-map default where `getMorphClass()` === FQCN and an unmapped model
// is allowed). Mirrors the arqel-dev/versioning #72 morph-map reset.
afterEach(function (): void {
    Relation::morphMap([], false);
    Relation::requireMorphMap(false);
});

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

it('finds history via the stateTransitions relationship under a custom morph map', function (): void {
    // The `stateTransitions()` morphMany queries `model_type = getMorphClass()`.
    // Under this map that resolves to the alias 'wf_order', so the writer must
    // store the alias too — keying on the raw FQCN leaves the relation empty.
    Relation::enforceMorphMap(['wf_order' => WorkflowOrder::class]);

    $order = WorkflowOrder::create(['order_state' => PendingState::class]);
    $order->transitionTo(PaidState::class, ['reason' => 'webhook']);

    // The documented relation must find the row it persisted.
    expect($order->stateTransitions()->count())->toBe(1);

    // Guard against false-positives: the row was stored under the morph alias,
    // not the FQCN, so the morphMany predicate is the thing under test.
    expect(StateTransition::query()->where('model_type', 'wf_order')->count())->toBe(1)
        ->and(StateTransition::query()->where('model_type', WorkflowOrder::class)->count())->toBe(0);

    // The field read path must resolve history consistently with the writer.
    $field = StateTransitionField::make('state')->record($order);

    expect($field->resolveHistory())->toHaveCount(1)
        ->and($field->resolveHistory()[0]['to'])->toBe(PaidState::class);
});
