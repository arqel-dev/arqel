<?php

declare(strict_types=1);

namespace Arqel\Auth;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Registry of global + computed abilities surfaced in shared
 * Inertia props (`auth.can.*`).
 *
 * Global abilities are checked through Laravel's Gate against the
 * authenticated user; computed abilities run a closure and are
 * useful for ad-hoc checks that don't fit a Policy method (e.g.
 * "user has at least one paid subscription").
 *
 * `resolveForUser($user)` is invoked once per request by the
 * Inertia middleware (CORE-007); results are cached in-memory for
 * the lifetime of the request via the protected `$resolved` map.
 */
final class AbilityRegistry
{
    /** @var array<int, string> */
    protected array $globalAbilities = [];

    /** @var array<string, Closure> */
    protected array $computedAbilities = [];

    /** @var array<string, array<string, bool>> */
    protected array $resolved = [];

    public function registerGlobal(string $ability): self
    {
        if (! in_array($ability, $this->globalAbilities, true)) {
            $this->globalAbilities[] = $ability;
        }

        return $this;
    }

    /**
     * @param array<int, string> $abilities
     */
    public function registerGlobals(array $abilities): self
    {
        foreach ($abilities as $ability) {
            $this->registerGlobal($ability);
        }

        return $this;
    }

    public function registerComputed(string $ability, Closure $callback): self
    {
        $this->computedAbilities[$ability] = $callback;

        return $this;
    }

    public function clear(): self
    {
        $this->globalAbilities = [];
        $this->computedAbilities = [];
        $this->resolved = [];

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getGlobalAbilities(): array
    {
        return $this->globalAbilities;
    }

    /**
     * @return array<int, string>
     */
    public function getComputedAbilities(): array
    {
        return array_keys($this->computedAbilities);
    }

    /**
     * Resolve every registered ability for `$user`, returning a
     * map `<ability, bool>` ready to inject into shared props.
     * Results are cached per-request (keyed by the user's
     * authIdentifier or `guest`).
     *
     * @return array<string, bool>
     */
    public function resolveForUser(?Authenticatable $user): array
    {
        $identifier = $user?->getAuthIdentifier();
        $cacheKey = $identifier === null ? 'guest' : (string) (is_scalar($identifier) ? $identifier : 'unknown');

        if (isset($this->resolved[$cacheKey])) {
            return $this->resolved[$cacheKey];
        }

        $result = [];

        foreach ($this->globalAbilities as $ability) {
            $result[$ability] = $this->resolveGlobal($user, $ability);
        }

        foreach ($this->computedAbilities as $ability => $callback) {
            $result[$ability] = (bool) $callback($user);
        }

        return $this->resolved[$cacheKey] = $result;
    }

    private function resolveGlobal(?Authenticatable $user, string $ability): bool
    {
        if ($user === null) {
            return false;
        }

        return Gate::forUser($user)->allows($ability);
    }
}
