<?php

declare(strict_types=1);

namespace Arqel\Tenant\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Strategy contract for extracting the "current tenant" from an
 * incoming request.
 *
 * Implementations (subdomain, path segment, header, session,
 * authenticated user) live under `Arqel\Tenant\Resolvers`. The
 * resolver returns `null` when no tenant can be determined for the
 * request — `TenantManager` decides whether that is acceptable
 * (e.g. central domain) or should abort.
 *
 * `identifierFor` is the cache key used by `TenantManager` to
 * memoise resolution per-request and to key per-tenant caches.
 */
interface TenantResolver
{
    public function resolve(Request $request): ?Model;

    /**
     * Stable string identifier for `$tenant`. Defaults to the
     * model's primary key (`getKey()`) but may be overridden so
     * cache keys are human-readable (e.g. subdomain string).
     */
    public function identifierFor(Model $tenant): string;
}
