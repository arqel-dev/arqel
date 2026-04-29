<?php

declare(strict_types=1);

namespace Arqel\Tenant\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Optional capability contract: resolvers that implement this
 * advertise they can enumerate the tenants available to a user
 * and persist a switch decision.
 *
 * `TenantManager` exposes the same surface (`availableFor`,
 * `canSwitchTo`, `switchTo`) and delegates to the active resolver
 * when it implements this contract. Resolvers that cannot persist
 * a switch (e.g. subdomain-based, where the choice is encoded in
 * the URL) simply omit the contract — controllers that depend on
 * switching will then surface a clear `LogicException` instead of
 * silently doing nothing.
 *
 * Built-in `AbstractTenantResolver` provides a default
 * implementation suitable for `AuthUserResolver`-style flows: it
 * scans a `tenants` (or configurable) relationship on the user
 * and writes the chosen tenant back to the user via a configured
 * column.
 */
interface SupportsTenantSwitching
{
    /**
     * @return array<int, Model> tenants the user may switch to.
     */
    public function availableFor(Authenticatable $user): array;

    public function canSwitchTo(Authenticatable $user, Model $tenant): bool;

    /**
     * Persist `$tenant` as the user's current tenant. The exact
     * mechanism is resolver-specific (DB column, session entry,
     * external API call).
     */
    public function switchTo(Authenticatable $user, Model $tenant): void;
}
