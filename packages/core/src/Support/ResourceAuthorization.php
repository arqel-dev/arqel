<?php

declare(strict_types=1);

namespace Arqel\Core\Support;

use Arqel\Core\Contracts\HasResource;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Single source of truth for "may this user even see this Resource?"
 * navigation-surface gating.
 *
 * Every navigation surface (the sidebar built by
 * {@see \Arqel\Core\Http\Middleware\HandleArqelInertiaRequests} and the
 * Cmd+K command palette built by
 * {@see \Arqel\Core\CommandPalette\Providers\NavigationCommandProvider})
 * must hide a Resource the user is denied `viewAny` on — otherwise the
 * forbidden feature name/link leaks (issues #118 and #129). Both call
 * {@see self::viewAnyDenied()} so the two surfaces stay symmetric and
 * cannot drift apart.
 */
final class ResourceAuthorization
{
    /**
     * Decide whether a Resource must be hidden because the given user is
     * denied `viewAny` on its model.
     *
     * Mirrors {@see \Arqel\Core\Http\Controllers\ResourceController}'s
     * `authorize()`: only consult the Gate when a `viewAny` gate OR a
     * Policy for the model exists. When neither does (scaffold apps), the
     * Resource is always shown — denying nothing — so the no-policy
     * baseline holds and nothing regresses.
     *
     * @param class-string<HasResource> $resourceClass
     */
    public static function viewAnyDenied(string $resourceClass, ?Authenticatable $user): bool
    {
        try {
            $modelClass = $resourceClass::getModel();
        } catch (Throwable) {
            // Resource without a declared model (scaffold/fixture) — there
            // is nothing to authorize against, so never hide it.
            return false;
        }

        if (! Gate::has('viewAny') && ! Gate::getPolicyFor($modelClass)) {
            return false;
        }

        return Gate::forUser($user)->denies('viewAny', $modelClass);
    }
}
