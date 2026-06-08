<?php

declare(strict_types=1);

namespace App\Workflow\Transitions;

use Illuminate\Contracts\Auth\Authenticatable;

final class ShippedToDelivered
{
    /** @return list<string> */
    public static function from(): array
    {
        return ['shipped'];
    }

    public static function to(): string
    {
        return 'delivered';
    }

    public static function authorizeFor(?Authenticatable $user, mixed $record): bool
    {
        return $user !== null;
    }
}
