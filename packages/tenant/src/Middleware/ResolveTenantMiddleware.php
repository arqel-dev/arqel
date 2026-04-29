<?php

declare(strict_types=1);

namespace Arqel\Tenant\Middleware;

use Arqel\Tenant\Exceptions\TenantNotFoundException;
use Arqel\Tenant\TenantManager;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the current tenant before the controller runs.
 *
 * Two modes (passed as middleware parameter):
 *   - `required` (default) — aborts with `TenantNotFoundException`
 *     (rendered as 404) when no tenant matches the request
 *   - `optional` — lets the request through with `null` tenant.
 *     Useful for marketing/landing pages that share middleware
 *     stack with the tenant-aware admin
 *
 * Usage:
 *   ->middleware(['web', 'auth', 'arqel.tenant'])
 *   ->middleware(['web', 'auth', 'arqel.tenant:optional'])
 *
 * Order matters: place after `auth` when using `AuthUserResolver`
 * — the resolver reads `$request->user()`.
 */
final class ResolveTenantMiddleware
{
    public const string MODE_REQUIRED = 'required';

    public const string MODE_OPTIONAL = 'optional';

    public function __construct(
        private readonly TenantManager $manager,
    ) {}

    public function handle(Request $request, Closure $next, string $mode = self::MODE_REQUIRED): mixed
    {
        $tenant = $this->manager->resolve($request);

        if ($tenant === null && $this->normaliseMode($mode) === self::MODE_REQUIRED) {
            throw new TenantNotFoundException(
                identifier: $request->getHost(),
            );
        }

        return $next($request);
    }

    private function normaliseMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return $mode === self::MODE_OPTIONAL ? self::MODE_OPTIONAL : self::MODE_REQUIRED;
    }
}
