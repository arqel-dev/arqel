<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Transition fixture cujo `authorizeFor()` (estático) sempre nega.
 */
final class DeniedTransition
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

    public static function authorizeFor(?Authenticatable $user, mixed $record): bool
    {
        return false;
    }
}
