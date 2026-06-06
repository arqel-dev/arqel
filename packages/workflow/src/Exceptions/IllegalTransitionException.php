<?php

declare(strict_types=1);

namespace Arqel\Workflow\Exceptions;

use RuntimeException;

/**
 * Lançada por `HasWorkflow::transitionTo()` quando o `newState` solicitado
 * não é alcançável a partir do state atual: nenhuma transition declarada na
 * `WorkflowDefinition` tem o state atual no seu `from()` e o `newState` como
 * destino (WF-006, eligibility check).
 *
 * Não relaxa nada — só enforça o grafo de transitions que o próprio model
 * declara. Models sem transitions (`transitions([])`) são free-form e nunca
 * disparam esta exceção.
 */
final class IllegalTransitionException extends RuntimeException
{
    public static function for(string $from, string $to): self
    {
        $fromLabel = $from === '' ? '(none)' : $from;

        return new self(sprintf(
            'Illegal workflow transition: no declared transition reaches "%s" from "%s".',
            $to,
            $fromLabel,
        ));
    }
}
