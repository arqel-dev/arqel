<?php

declare(strict_types=1);

namespace Arqel\Tenant;

/**
 * Singleton entry point for the current tenant context.
 *
 * Phase 2 scaffold — the public API (current/setCurrent/forget,
 * resolver registration, scoping helpers) lands in TENANT-003.
 * For now the class only exists so:
 *   1. `TenantServiceProvider` can bind it as a singleton without
 *      tripping autoloading.
 *   2. Downstream packages can type-hint `TenantManager` from day
 *      one without nullability.
 *
 * Do **not** rely on internal state here yet — every public
 * method below is a no-op stub.
 */
final class TenantManager
{
    /**
     * Returns the currently-resolved tenant identity, or null when
     * the request is not yet scoped (or runs in a non-tenant
     * context). Will be wired in TENANT-003.
     */
    public function current(): mixed
    {
        return null;
    }

    /**
     * Whether a tenant has been resolved for this request. Stub
     * until TENANT-003 lands the real lookup.
     */
    public function hasCurrent(): bool
    {
        return false;
    }
}
