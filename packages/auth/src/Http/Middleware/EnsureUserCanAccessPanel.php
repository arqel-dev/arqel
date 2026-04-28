<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Middleware;

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
 * Guests are redirected to `arqel.auth.guard` (config) and signed
 * users without the ability get a 403. When the ability has not
 * been registered with Laravel's Gate the middleware short-circuits
 * to "allow" so a fresh install boots without panel-level gating.
 */
final class EnsureUserCanAccessPanel
{
    public const string DEFAULT_ABILITY = 'viewAdminPanel';

    public function handle(Request $request, Closure $next, string $ability = self::DEFAULT_ABILITY): mixed
    {
        $user = $request->user();

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
