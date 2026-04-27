<?php

declare(strict_types=1);

namespace Arqel\Core\Contracts;

/**
 * Implemented by Resources that declare an Eloquent policy class.
 *
 * Resources without this contract fall back to the conventional
 * mapping ({Model}Policy in `App\Policies`), resolved by Laravel's
 * Gate auto-discovery. Returning null here also defers to that
 * default — the contract exists so explicit declarations are
 * type-safe.
 */
interface HasPolicies
{
    /**
     * @return class-string|null
     */
    public static function getPolicy(): ?string;
}
