<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Middleware;

use Arqel\Auth\Concerns\ResolvesPanelGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Gate every panel route on the user holding a configurable
 * ability. Default ability is `viewAdminPanel` and can be
 * overridden by binding the middleware with a parameter:
 *
 *     ->middleware(EnsureUserCanAccessPanel::class.':viewAdminPanel')
 *
 * The active user is resolved against the panel's configured guard
 * (`Panel::authGuard(...)`, falling back to `arqel.auth.guard`, then
 * `web`). Guests get a 401 and signed users without the ability get a
 * 403. When the ability has not been registered with Laravel's Gate
 * the middleware short-circuits to "allow" so a fresh install boots
 * without panel-level gating.
 */
final class EnsureUserCanAccessPanel
{
    use ResolvesPanelGuard;

    public const string DEFAULT_ABILITY = 'viewAdminPanel';

    public function handle(Request $request, Closure $next, string $ability = self::DEFAULT_ABILITY): mixed
    {
        $user = $request->user($this->resolvePanelGuard());

        if ($user === null) {
            abort(HttpResponse::HTTP_UNAUTHORIZED);
        }

        if (! Gate::has($ability)) {
            return $next($request);
        }

        if (Gate::forUser($user)->denies($ability)) {
            abort(HttpResponse::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
