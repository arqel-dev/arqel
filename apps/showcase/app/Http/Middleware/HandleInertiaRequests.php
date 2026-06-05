<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * App-level Inertia middleware.
 *
 * Pinned to `arqel.layout` (the Blade root published by
 * `arqel:install`) so login/register/etc. — which do not pass
 * through `HandleArqelInertiaRequests` from the core — still
 * render under the same root template.
 *
 * The tenant context (`{ current, available }`) is shared GLOBALLY via
 * `Inertia::share` in `ArqelServiceProvider`, so it reaches the panel
 * resource pages (which run through the core's Inertia middleware, not
 * this one) too.
 */
final class HandleInertiaRequests extends Middleware
{
    /** @var string */
    protected $rootView = 'arqel.layout';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => fn () => [
                'user' => $request->user(),
            ],
            'app' => [
                'name' => config('app.name'),
            ],
        ]);
    }
}
