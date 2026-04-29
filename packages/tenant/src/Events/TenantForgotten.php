<?php

declare(strict_types=1);

namespace Arqel\Tenant\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Fired when `TenantManager` drops a previously-current tenant
 * — through `forget()`, `set(null)`, or exit of a `runFor()`
 * scope that swallowed an outer tenant.
 *
 * Listeners typically clear per-tenant caches.
 */
final class TenantForgotten
{
    public function __construct(
        public readonly Model $tenant,
    ) {}
}
