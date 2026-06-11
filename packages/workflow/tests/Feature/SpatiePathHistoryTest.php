<?php

declare(strict_types=1);

use Arqel\Workflow\Models\StateTransition;
use Arqel\Workflow\Tests\Fixtures\PaidState;
use Arqel\Workflow\Tests\Fixtures\SpatieStyleState;
use Arqel\Workflow\Tests\Fixtures\SpatieWorkflowOrder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/*
 * #250 — footgun 3: spatie-path history records the raw `to` argument.
 *
 * On the spatie path `HasWorkflow::transitionTo()` delegated the mutation to
 * the state object's own `transitionTo()` and then fired `StateTransitioned`
 * with the *raw* `$newState` argument. If spatie normalizes the persisted value
 * (FQCN vs slug, or a no-op that keeps the previous token), the persisted
 * history `to_state` diverges from the model's *real* state column.
 *
 * The recorded history must match the state actually persisted on the model —
 * i.e. `resolveStateKey($model->{$field})` — not the raw argument.
 *
 * The `SpatieStyleState` stub's `transitionTo()` is a spy that does NOT mutate
 * `order_state`; the real column therefore stays `SpatieStyleState::class`,
 * which is exactly the kind of normalization divergence this guards.
 */

beforeEach(function (): void {
    SpatieStyleState::reset();
    Gate::define('transition-spatie-style-to-paid', static fn (?Authenticatable $user): bool => true);
});

it('records the persisted state in spatie-path history, not the raw argument', function (): void {
    $order = SpatieWorkflowOrder::create(['order_state' => SpatieStyleState::class]);

    $order->transitionTo(PaidState::class);

    // The state actually persisted on the model (resolved canonically).
    $realState = $order->getCurrentStateMetadata();
    // The stub does not mutate the column, so the real persisted state is the
    // original SpatieStyleState — never the raw PaidState argument.
    expect($order->order_state?->name)->toBe(SpatieStyleState::class);

    /** @var StateTransition|null $row */
    $row = StateTransition::query()->latest('id')->first();

    expect($row)->not->toBeNull()
        ->and($row->to_state)->toBe(SpatieStyleState::class);

    // Guard the metadata accessor exists so $realState is a meaningful read.
    expect($realState)->toBeArray();
});
