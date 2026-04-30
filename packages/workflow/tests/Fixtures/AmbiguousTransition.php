<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

/**
 * Transition fixture sem `authorizeFor()` — exercita o fallback de Gate
 * + deny-by-default do `TransitionAuthorizer`.
 */
final class AmbiguousTransition
{
    /** @return list<class-string> */
    public static function from(): array
    {
        return [PendingState::class];
    }

    public static function to(): string
    {
        return PaidState::class;
    }
}
