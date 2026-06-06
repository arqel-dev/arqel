<?php

declare(strict_types=1);

namespace Arqel\Workflow\Exceptions;

use RuntimeException;

/**
 * Lançada por `HasWorkflow::transitionTo()` quando existe uma transition
 * declarada que torna `newState` alcançável, mas o `TransitionAuthorizer`
 * nega o par `(transition, user, record)` (WF-006, server-side authorization).
 *
 * O authorizer é deny-by-default — esta exceção é o enforcement server-side
 * que o package anunciava mas que o caminho fallback do trait nunca chamava.
 */
final class UnauthorizedTransitionException extends RuntimeException
{
    public static function for(string $to): self
    {
        return new self(sprintf(
            'Unauthorized workflow transition: the current user may not transition to "%s".',
            $to,
        ));
    }
}
