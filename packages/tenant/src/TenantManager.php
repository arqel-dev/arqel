<?php

declare(strict_types=1);

namespace Arqel\Tenant;

use Arqel\Tenant\Contracts\TenantResolver;
use Arqel\Tenant\Events\TenantForgotten;
use Arqel\Tenant\Events\TenantResolved;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LogicException;

/**
 * Central registry for the "current tenant" during a request.
 *
 * Bound as a singleton by `TenantServiceProvider`. Three pieces
 * of state:
 *
 *  1. **Current tenant** — `?Model` set by `resolve()` /
 *     `set()` / inside `runFor()`. `current()` reads it.
 *  2. **Resolved flag** — true after the resolver runs (memoises
 *     lookups for the rest of the request).
 *  3. **Resolver** — optional `TenantResolver` injected by the
 *     provider; missing when the app uses `set()` / `runFor()`
 *     directly (queue workers, console commands).
 *
 * Events fire through the container's `Dispatcher` when bound.
 * Apps can opt out by binding a Null dispatcher in tests, or
 * skipping the dispatcher constructor argument entirely.
 */
final class TenantManager
{
    private ?Model $currentTenant = null;

    private bool $resolved = false;

    public function __construct(
        private readonly ?TenantResolver $resolver = null,
        private readonly ?Dispatcher $events = null,
    ) {}

    /**
     * Resolve the current tenant for `$request` exactly once
     * per-request. Subsequent calls return the cached result
     * until `forget()` is invoked.
     */
    public function resolve(Request $request): ?Model
    {
        if ($this->resolved) {
            return $this->currentTenant;
        }

        $this->resolved = true;

        if ($this->resolver === null) {
            return null;
        }

        $tenant = $this->resolver->resolve($request);

        if ($tenant !== null) {
            $this->currentTenant = $tenant;
            $this->events?->dispatch(new TenantResolved($tenant));
        }

        return $this->currentTenant;
    }

    /**
     * Programmatically set the current tenant. Used by queue
     * workers, console commands, or tests that need to bypass
     * the request-bound resolver. Passing `null` is equivalent
     * to `forget()` and dispatches `TenantForgotten` for the
     * previous tenant when there was one.
     */
    public function set(?Model $tenant): void
    {
        $previous = $this->currentTenant;
        $this->currentTenant = $tenant;
        $this->resolved = $tenant !== null;

        if ($tenant !== null && $tenant !== $previous) {
            $this->events?->dispatch(new TenantResolved($tenant));
        }

        if ($tenant === null && $previous !== null) {
            $this->events?->dispatch(new TenantForgotten($previous));
        }
    }

    /**
     * Run `$callback` with `$tenant` as the current scope, then
     * restore the previous tenant — even when the callback
     * throws. Useful for background jobs that act on behalf of a
     * specific tenant.
     *
     * @template T
     *
     * @param Closure(): T $callback
     *
     * @return T
     */
    public function runFor(Model $tenant, Closure $callback): mixed
    {
        $previousTenant = $this->currentTenant;
        $previousResolved = $this->resolved;

        $this->currentTenant = $tenant;
        $this->resolved = true;
        $this->events?->dispatch(new TenantResolved($tenant));

        try {
            return $callback();
        } finally {
            $this->currentTenant = $previousTenant;
            $this->resolved = $previousResolved;
        }
    }

    /**
     * Drop the current tenant — `current()` returns null and
     * `resolve()` re-runs the resolver on the next call.
     */
    public function forget(): void
    {
        $previous = $this->currentTenant;
        $this->currentTenant = null;
        $this->resolved = false;

        if ($previous !== null) {
            $this->events?->dispatch(new TenantForgotten($previous));
        }
    }

    public function current(): ?Model
    {
        return $this->currentTenant;
    }

    /**
     * Throws when no tenant is set — convenience for code paths
     * that assume tenant scoping (Repository methods, Resource
     * controllers, etc.) and want a loud failure mode.
     */
    public function currentOrFail(): Model
    {
        if ($this->currentTenant === null) {
            throw new LogicException(
                'No current tenant — TenantManager::set() or resolve() must be called first.',
            );
        }

        return $this->currentTenant;
    }

    public function hasCurrent(): bool
    {
        return $this->currentTenant !== null;
    }

    public function id(): int|string|null
    {
        if ($this->currentTenant === null) {
            return null;
        }

        $key = $this->currentTenant->getKey();

        return is_int($key) || is_string($key) ? $key : null;
    }

    /**
     * Whether the resolver has executed for the current request.
     * `set()` and `runFor()` mark the manager as resolved as a
     * side effect.
     */
    public function resolved(): bool
    {
        return $this->resolved;
    }

    /**
     * Identifier produced by the resolver for the current
     * tenant. Falls back to `(string) id()` when no resolver is
     * bound (set()/runFor() paths). Acts as a stable cache key
     * for per-tenant memoisation.
     */
    public function identifier(): string
    {
        if ($this->currentTenant === null) {
            return '';
        }

        if ($this->resolver !== null) {
            return $this->resolver->identifierFor($this->currentTenant);
        }

        $id = $this->id();

        return $id === null ? '' : (string) $id;
    }
}
