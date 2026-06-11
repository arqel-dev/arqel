<?php

declare(strict_types=1);

namespace Arqel\Actions\Concerns;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Per-action authorization.
 *
 * `authorize()` accepts EITHER form (and they compose):
 *   - a Closure receiving `(?Authenticatable $user, mixed $record = null)`
 *     that returns bool — full custom predicate; or
 *   - a string Gate ability (e.g. `'refund'`) checked via
 *     `Gate::forUser($user)->allows($ability, $record)` against the bound
 *     record (or null for record-less actions).
 *
 * Precedence: a declared closure and a declared string ability are both
 * gates that must pass (logical AND). Declaring neither keeps the default
 * permissive behaviour (always authorized) — the resource-side action Gate
 * (`update`/`viewAny`) remains the outer guard. This deliberately does NOT
 * flip actions to deny-by-default; it only ADDS an explicit per-action
 * ability mechanism for devs who want it.
 */
trait HasAuthorization
{
    protected ?Closure $authorize = null;

    protected ?string $authorizeAbility = null;

    public function authorize(Closure|string $callback): static
    {
        if (is_string($callback)) {
            $this->authorizeAbility = $callback;
        } else {
            $this->authorize = $callback;
        }

        return $this;
    }

    public function canBeExecutedBy(?Authenticatable $user, mixed $record = null): bool
    {
        if ($this->authorizeAbility !== null
            && ! Gate::forUser($user)->allows($this->authorizeAbility, $record)) {
            return false;
        }

        if ($this->authorize !== null) {
            return (bool) ($this->authorize)($user, $record);
        }

        return true;
    }
}
