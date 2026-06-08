<?php

declare(strict_types=1);

namespace App\Workflow\Transitions;

use Illuminate\Contracts\Auth\Authenticatable;

final class AnyToCancelled
{
    /** @return list<string> */
    public static function from(): array
    {
        return ['pending', 'paid', 'shipped'];
    }

    public static function to(): string
    {
        return 'cancelled';
    }

    public static function authorizeFor(?Authenticatable $user, mixed $record): bool
    {
        return $user !== null;
    }
}
