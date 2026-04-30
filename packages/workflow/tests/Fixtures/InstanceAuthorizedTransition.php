<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Transition fixture com `authorizeFor()` de instância (não estático).
 */
final class InstanceAuthorizedTransition
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

    public function authorizeFor(?Authenticatable $user, mixed $record): bool
    {
        return true;
    }
}
