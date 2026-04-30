<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

/**
 * Transition fixture cujo `authorizeFor()` lança exceção — deve degradar
 * para `false` (deny-on-failure).
 */
final class ThrowingTransition
{
    public static function authorizeFor(?Authenticatable $user, mixed $record): bool
    {
        throw new RuntimeException('boom');
    }
}
