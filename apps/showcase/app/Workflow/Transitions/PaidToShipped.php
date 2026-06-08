<?php

declare(strict_types=1);

namespace App\Workflow\Transitions;

use Illuminate\Contracts\Auth\Authenticatable;

final class PaidToShipped
{
    /** @return list<string> */
    public static function from(): array
    {
        return ['paid'];
    }

    public static function to(): string
    {
        return 'shipped';
    }

    public static function authorizeFor(?Authenticatable $user, mixed $record): bool
    {
        return $user !== null;
    }
}
