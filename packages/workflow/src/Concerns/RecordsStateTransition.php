<?php

declare(strict_types=1);

namespace Arqel\Workflow\Concerns;

use Arqel\Workflow\Events\StateTransitioned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Trait opcional para classes de transição (e.g. uma `Spatie\ModelStates\Transition`)
 * que queiram emitir o evento canônico `StateTransitioned` dentro do seu próprio
 * `handle()` — útil quando o user-land prefere disparar a transição via classes
 * spatie em vez de `HasWorkflow::transitionTo()`.
 *
 * O trait NÃO assume nenhuma API spatie; é puramente um helper para o evento.
 */
trait RecordsStateTransition
{
    /**
     * Dispara `StateTransitioned` capturando `Auth::id()` automaticamente.
     *
     * @param  array<string, mixed>  $context
     */
    protected function recordTransition(
        Model $record,
        string $fromState,
        string $toState,
        array $context = [],
    ): void {
        if (config('arqel-workflow.audit.enabled', true) === false) {
            return;
        }

        if (config('arqel-workflow.audit.log_via', 'event') !== 'event') {
            return;
        }

        $userId = Auth::id();

        event(new StateTransitioned(
            record: $record,
            from: $fromState,
            to: $toState,
            userId: is_int($userId) ? $userId : null,
            context: $context,
        ));
    }
}
