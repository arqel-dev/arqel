<?php

declare(strict_types=1);

use Arqel\Workflow\Tests\Fixtures\PaidState;
use Arqel\Workflow\Tests\Fixtures\PendingState;
use Arqel\Workflow\WorkflowDefinition;
use Illuminate\Support\Facades\Lang;

it('localises a translation-key state label through the active locale', function (): void {
    Lang::addLines([
        'workflow.states.pending' => 'Aguardando aprovação',
    ], 'pt_BR', 'arqel');

    app()->setLocale('pt_BR');

    $def = WorkflowDefinition::make('state')
        ->states([
            PendingState::class => ['label' => 'arqel::workflow.states.pending', 'color' => 'warning', 'icon' => 'clock'],
        ]);

    $meta = $def->getStateMetadata(PendingState::class);

    expect($meta)->not->toBeNull()
        ->and($meta['label'])->toBe('Aguardando aprovação');

    expect($def->getStates()[PendingState::class]['label'])->toBe('Aguardando aprovação');
    expect($def->toArray()['states'][PendingState::class]['label'])->toBe('Aguardando aprovação');
});

it('passes a literal state label through untouched', function (): void {
    app()->setLocale('pt_BR');

    $def = WorkflowDefinition::make('state')
        ->states([
            PaidState::class => ['label' => 'Pending Review', 'color' => 'info', 'icon' => 'check'],
        ]);

    expect($def->getStateMetadata(PaidState::class)['label'])->toBe('Pending Review')
        ->and($def->getStates()[PaidState::class]['label'])->toBe('Pending Review')
        ->and($def->toArray()['states'][PaidState::class]['label'])->toBe('Pending Review');
});
