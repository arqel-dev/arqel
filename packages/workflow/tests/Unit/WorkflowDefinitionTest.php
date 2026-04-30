<?php

declare(strict_types=1);

use Arqel\Workflow\Tests\Fixtures\AnyToCancelled;
use Arqel\Workflow\Tests\Fixtures\PaidState;
use Arqel\Workflow\Tests\Fixtures\PendingState;
use Arqel\Workflow\Tests\Fixtures\PendingToPaid;
use Arqel\Workflow\WorkflowDefinition;

it('builds a definition with field, states and transitions', function (): void {
    $def = WorkflowDefinition::make('order_state')
        ->states([
            PendingState::class => ['label' => 'Pending', 'color' => 'warning', 'icon' => 'clock'],
            PaidState::class => ['label' => 'Paid', 'color' => 'info', 'icon' => 'credit-card'],
        ])
        ->transitions([PendingToPaid::class, AnyToCancelled::class]);

    expect($def->getField())->toBe('order_state')
        ->and($def->getStates())->toHaveCount(2)
        ->and($def->getTransitions())->toBe([PendingToPaid::class, AnyToCancelled::class]);
});

it('rejects an empty field', function (): void {
    WorkflowDefinition::make('   ');
})->throws(InvalidArgumentException::class);

it('returns null for unknown state metadata', function (): void {
    $def = WorkflowDefinition::make('state')
        ->states([PendingState::class => ['label' => 'Pending', 'color' => 'warning', 'icon' => 'clock']]);

    expect($def->getStateMetadata('NotRegistered\\State'))->toBeNull()
        ->and($def->getStateMetadata(PendingState::class))->toBe([
            'label' => 'Pending',
            'color' => 'warning',
            'icon' => 'clock',
        ]);
});

it('fills missing label/color/icon with sensible fallbacks', function (): void {
    $def = WorkflowDefinition::make('state')
        ->states([
            PendingState::class => [],
        ]);

    $meta = $def->getStateMetadata(PendingState::class);

    expect($meta)->toBe([
        'label' => 'Pending State',
        'color' => 'secondary',
        'icon' => 'circle',
    ]);
});

it('serialises to array for React consumption', function (): void {
    $def = WorkflowDefinition::make('order_state')
        ->states([
            PendingState::class => ['label' => 'Pending', 'color' => 'warning', 'icon' => 'clock'],
        ])
        ->transitions([PendingToPaid::class]);

    $payload = $def->toArray();

    expect($payload)->toHaveKeys(['field', 'states', 'transitions'])
        ->and($payload['field'])->toBe('order_state')
        ->and($payload['states'])->toHaveKey(PendingState::class)
        ->and($payload['transitions'])->toBe([PendingToPaid::class]);
});

it('rejects non-string keys in the state map', function (): void {
    /** @phpstan-ignore-next-line — intentionally invalid input */
    WorkflowDefinition::make('state')->states([0 => ['label' => 'x']]);
})->throws(InvalidArgumentException::class);

it('rejects non-string entries in the transition list', function (): void {
    /** @phpstan-ignore-next-line — intentionally invalid input */
    WorkflowDefinition::make('state')->transitions(['']);
})->throws(InvalidArgumentException::class);
