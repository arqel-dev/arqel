<?php

declare(strict_types=1);

namespace Arqel\Workflow\Listeners;

use Arqel\Workflow\Events\StateTransitioned;
use Arqel\Workflow\Models\StateTransition;
use Throwable;

/**
 * Listener auto-registado pelo `WorkflowServiceProvider` que persiste
 * cada `StateTransitioned` em `arqel_state_transitions` (WF-007).
 *
 * Skip silencioso quando `arqel-workflow.history.enabled === false`.
 * Captura `Throwable` defensivamente — falha de persistência não deve
 * impedir transições do domínio (audit é best-effort).
 */
final class PersistStateTransitionToHistory
{
    public function handle(StateTransitioned $event): void
    {
        if (config('arqel-workflow.history.enabled', true) === false) {
            return;
        }

        try {
            StateTransition::query()->create([
                'model_type' => $event->record::class,
                'model_id' => $event->record->getKey(),
                'from_state' => $event->from !== '' ? $event->from : null,
                'to_state' => $event->to,
                'transitioned_by_user_id' => $event->userId,
                'metadata' => $event->context !== [] ? $event->context : null,
            ]);
        } catch (Throwable) {
            // best-effort: tabela pode não existir (migration não rodada).
        }
    }
}
