<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

/**
 * Transition fixture whose `from()` lists multiple states, including the
 * record's current state. Mirrors the issue #154 repro: a single transition
 * declaring `from() = ['open', 'in_progress']` and `to() = 'resolved'`.
 *
 * `resolveAvailableTransitions()` must emit exactly ONE entry for this
 * transition (with `from` = the record's current state), not one entry per
 * declared from-state.
 */
final class MultiFromToResolved
{
    /** @return list<string> */
    public static function from(): array
    {
        return ['open', 'in_progress'];
    }

    public static function to(): string
    {
        return 'resolved';
    }
}
