<?php

declare(strict_types=1);

use Arqel\Workflow\Fields\StateTransitionField;
use Arqel\Workflow\Tests\Fixtures\MultiFromWorkflowTicket;
use Arqel\Workflow\Tests\Fixtures\PaidState;
use Arqel\Workflow\Tests\Fixtures\PendingState;
use Arqel\Workflow\Tests\Fixtures\WorkflowOrder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

it('exposes type, component, and is created via make()', function (): void {
    $field = StateTransitionField::make('state');

    expect($field)->toBeInstanceOf(StateTransitionField::class)
        ->and($field->getType())->toBe('state-transition')
        ->and($field->getComponent())->toBe('arqel-dev/workflow/StateTransition')
        ->and($field->getName())->toBe('state');
});

it('propagates showDescription and showHistory flags into props', function (): void {
    $field = StateTransitionField::make('state')
        ->showDescription()
        ->showHistory();

    $props = $field->getTypeSpecificProps();

    expect($props['showDescription'])->toBeTrue()
        ->and($props['showHistory'])->toBeTrue();

    $field2 = StateTransitionField::make('state')
        ->showDescription(false)
        ->showHistory(false);

    $props2 = $field2->getTypeSpecificProps();

    expect($props2['showDescription'])->toBeFalse()
        ->and($props2['showHistory'])->toBeFalse();
});

it('returns null currentState and empty transitions when no record bound', function (): void {
    $field = StateTransitionField::make('state');

    $props = $field->getTypeSpecificProps();

    expect($props['currentState'])->toBeNull()
        ->and($props['transitions'])->toBe([])
        ->and($props['history'])->toBe([]);
});

it('mirrors the record current state in props', function (): void {
    $order = new WorkflowOrder;
    $order->order_state = PendingState::class;

    $field = StateTransitionField::make('state')->record($order);

    $current = $field->resolveCurrentState();

    expect($current)->not->toBeNull();
    /** @var array{name: string, label: string, color: ?string, icon: ?string} $current */
    expect($current['name'])->toBe(PendingState::class)
        ->and($current['label'])->toBe('Pending')
        ->and($current['color'])->toBe('warning')
        ->and($current['icon'])->toBe('clock');
});

it('lists available transitions with from/to/label/authorized keys', function (): void {
    $order = new WorkflowOrder;
    $order->order_state = PendingState::class;

    $field = StateTransitionField::make('state')->record($order);

    $transitions = $field->resolveAvailableTransitions();

    expect($transitions)->not->toBeEmpty();

    foreach ($transitions as $transition) {
        expect($transition)->toHaveKeys(['from', 'to', 'label', 'authorized'])
            ->and($transition['authorized'])->toBeBool();
    }

    $froms = array_map(fn (array $t): string => $t['from'], $transitions);
    expect($froms)->toContain(PendingState::class);
});

it('emits exactly one entry for a multi-from transition matching the current state', function (): void {
    // #154: a single transition whose from() lists multiple states, including
    // the record's current state, must produce ONE entry (not one per declared
    // from-state), with from = the record's actual current state. Pre-fix the
    // inner loop over every declared from emitted a duplicate button carrying a
    // from that is not the current state.
    $ticket = new MultiFromWorkflowTicket;
    $ticket->ticket_state = 'open';

    $field = StateTransitionField::make('state')->record($ticket);

    $transitions = $field->resolveAvailableTransitions();

    expect($transitions)->toHaveCount(1);

    $entry = $transitions[0];
    expect($entry['from'])->toBe('open')
        ->and($entry['to'])->toBe('resolved')
        ->and($entry)->toHaveKeys(['from', 'to', 'label', 'authorized']);

    // No spurious entry carrying the other declared from-state.
    $froms = array_map(static fn (array $t): string => $t['from'], $transitions);
    expect($froms)->not->toContain('in_progress');
});

it('omits a multi-from transition when the current state is not in its from list', function (): void {
    $ticket = new MultiFromWorkflowTicket;
    $ticket->ticket_state = 'resolved';

    $field = StateTransitionField::make('state')->record($ticket);

    expect($field->resolveAvailableTransitions())->toBe([]);
});

it('still emits one entry each for single-from and from()-less transitions', function (): void {
    // Regression guard: the dedupe fix must not change the one-entry-per-applicable
    // behaviour for single-from transitions (PendingToPaid) nor for from()-less
    // ones (AnyToCancelled, always available).
    $order = new WorkflowOrder;
    $order->order_state = PendingState::class;

    $transitions = StateTransitionField::make('state')->record($order)->resolveAvailableTransitions();

    $tos = array_map(static fn (array $t): string => $t['to'], $transitions);

    // PendingToPaid (single-from, applicable) + AnyToCancelled (from()-less,
    // always available). PaidToShipped does not apply from Pending.
    expect($tos)->toContain('Paid')
        ->and($tos)->toContain('Cancelled')
        ->and($tos)->not->toContain('Shipped');

    // One entry per applicable transition (no duplicates).
    expect(count($tos))->toBe(count(array_unique($tos)));

    // Every emitted entry carries the record's current state as `from`.
    foreach ($transitions as $entry) {
        expect($entry['from'])->toBe(PendingState::class);
    }
});

it('produces JSON-encodable props including transitions and history', function (): void {
    $order = new WorkflowOrder;
    $order->order_state = PaidState::class;

    $field = StateTransitionField::make('state')
        ->showDescription()
        ->showHistory()
        ->record($order);

    $encoded = json_encode($field->getTypeSpecificProps());

    expect($encoded)->toBeString()
        ->and($encoded)->not->toBe('');
    expect(json_decode((string) $encoded, true))->toBeArray();
});

it('respects transitionsAttribute setter', function (): void {
    $field = StateTransitionField::make('state')->transitionsAttribute('order_state');

    expect($field->getTransitionsAttribute())->toBe('order_state')
        ->and($field->getTypeSpecificProps()['transitionsAttribute'])->toBe('order_state');
});

it('emits the target segment as the to token for a class-name-convention transition', function (): void {
    // PendingToPaid declares no static to(); the field must derive the target
    // segment ('Paid') the same way the write path + authorizer do, not the
    // full short class name ('PendingToPaid'). Otherwise the rendered button's
    // payload slugifies to 'pending-to-paid' and never matches the declared
    // target 'paid', making the advertised transition un-actionable (#119).
    $order = new WorkflowOrder;
    $order->order_state = PendingState::class;

    $field = StateTransitionField::make('state')->record($order);

    $tos = array_map(
        static fn (array $transition): string => $transition['to'],
        $field->resolveAvailableTransitions(),
    );

    expect($tos)->toContain('Paid')
        ->and($tos)->not->toContain('PendingToPaid');
});

it('emits a to token that round-trips through the write path without throwing', function (): void {
    // The field-advertised 'to' token must be accepted by the write path for a
    // legal transition. Pre-fix the field emitted 'PendingToPaid' while
    // transitionTo() resolves the declared target to 'Paid', so mapping the
    // advertised token back to its state and posting it raised
    // IllegalTransitionException (#119).
    $order = WorkflowOrder::create(['order_state' => PendingState::class]);

    $field = StateTransitionField::make('state')->record($order);

    $tos = array_map(
        static fn (array $transition): string => $transition['to'],
        $field->resolveAvailableTransitions(),
    );

    // The Pending->Paid transition (no static to(); class-name convention)
    // must advertise 'Paid', the same target the write path resolves.
    expect($tos)->toContain('Paid');
    $to = 'Paid';

    // Map the advertised token back to the declared target FQCN the way a form
    // submission would; a divergent token (pre-fix 'PendingToPaid') would not
    // resolve to PaidState here and the write path would then raise
    // IllegalTransitionException.
    $candidates = [PaidState::class];
    $target = null;
    foreach ($candidates as $state) {
        if (str_contains(strtolower($state), strtolower($to))) {
            $target = $state;
            break;
        }
    }

    expect($target)->toBe(PaidState::class);

    Gate::define('transition-pending-to-paid', static fn (?Authenticatable $user): bool => true);

    $order->transitionTo(PaidState::class);

    expect($order->fresh()?->order_state)->toBe(PaidState::class);
});
