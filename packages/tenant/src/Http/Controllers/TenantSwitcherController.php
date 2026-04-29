<?php

declare(strict_types=1);

namespace Arqel\Tenant\Http\Controllers;

use Arqel\Tenant\Events\TenantSwitched;
use Arqel\Tenant\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * HTTP entrypoints for the tenant switcher (RF-MT-04).
 *
 * `switch` performs the active-tenant change for the authenticated
 * user and dispatches `TenantSwitched`. `list` returns the
 * (current, available) pair as JSON for sidebar/menu rendering on
 * the client. Both endpoints require an authenticated user and a
 * resolver that implements `SupportsTenantSwitching`; otherwise
 * the manager returns empty/false and the controller surfaces a
 * 403/404 as appropriate.
 */
final class TenantSwitcherController
{
    public function switch(Request $request, TenantManager $manager, string $tenantId): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $tenant = $this->findAvailableTenant($manager, $user, $tenantId);
        abort_if($tenant === null, 404);
        abort_unless($manager->canSwitchTo($user, $tenant), 403);

        $from = $manager->current();
        $manager->switchTo($user, $tenant);

        event(new TenantSwitched(from: $from, to: $tenant, user: $user));

        return redirect()->intended('/admin');
    }

    public function list(Request $request, TenantManager $manager): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $available = $manager->availableFor($user);

        return response()->json([
            'current' => $this->serialise($manager->current()),
            'available' => array_map(fn (Model $tenant): array => $this->serialise($tenant), $available),
        ]);
    }

    /**
     * Find a tenant in the user's available set by string identifier.
     * Returns null when the resolver does not support switching
     * (manager.availableFor returns empty in that case anyway).
     */
    private function findAvailableTenant(TenantManager $manager, mixed $user, string $tenantId): ?Model
    {
        foreach ($manager->availableFor($user) as $candidate) {
            if ((string) $candidate->getKey() === $tenantId) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array{id: mixed, name: ?string, slug: ?string, logo: ?string}|null
     */
    private function serialise(?Model $tenant): ?array
    {
        if ($tenant === null) {
            return null;
        }

        return [
            'id' => $tenant->getKey(),
            'name' => $tenant->getAttribute('name'),
            'slug' => $tenant->getAttribute('slug'),
            'logo' => $tenant->getAttribute('logo'),
        ];
    }
}
