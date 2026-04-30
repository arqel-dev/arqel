<?php

declare(strict_types=1);

namespace Arqel\Tenant\Middleware;

use Arqel\Tenant\TenantManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate routes by tenant subscription features.
 *
 * Resolves the current tenant via `TenantManager::current()` and
 * checks whether the requested feature is enabled. The tenant
 * model must expose a `hasFeature(string): bool` method —
 * typically by using the {@see \Arqel\Tenant\Concerns\HasFeatures}
 * trait.
 *
 * Outcomes:
 *   - no current tenant → 404 (consistent with `arqel.tenant`
 *     middleware semantics)
 *   - tenant doesn't expose `hasFeature()` → 500 with an
 *     actionable message pointing at the trait
 *   - feature disabled → 402 Payment Required with a structured
 *     JSON payload `{error, feature, message}` so the frontend
 *     can route the user to upsell flows
 *   - feature enabled → next($request)
 *
 * Usage:
 *   ->middleware(['web', 'auth', 'arqel.tenant', 'arqel.tenant.feature:analytics'])
 */
final class RequireTenantFeature
{
    public function __construct(
        private readonly TenantManager $manager,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): mixed
    {
        $tenant = $this->manager->current();

        if ($tenant === null) {
            abort(Response::HTTP_NOT_FOUND, 'No current tenant.');
        }

        if (! method_exists($tenant, 'hasFeature')) {
            abort(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Tenant model must use the Arqel\\Tenant\\Concerns\\HasFeatures trait or implement hasFeature(string): bool to use the arqel.tenant.feature middleware.',
            );
        }

        if ($tenant->hasFeature($feature) === false) {
            return new JsonResponse([
                'error' => 'feature_not_available',
                'feature' => $feature,
                'message' => "The '{$feature}' feature is not available on your current plan.",
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return $next($request);
    }
}
