<?php

declare(strict_types=1);

use Arqel\Workflow\Fields\StateTransitionField;
use Arqel\Workflow\Tests\Fixtures\PaidState;
use Arqel\Workflow\Tests\Fixtures\PendingState;
use Arqel\Workflow\Tests\Fixtures\WorkflowOrder;

it('exposes type, component, and is created via make()', function (): void {
    $field = StateTransitionField::make('state');

    expect($field)->toBeInstanceOf(StateTransitionField::class)
        ->and($field->getType())->toBe('state-transition')
        ->and($field->getComponent())->toBe('arqel/workflow/StateTransition')
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
