<?php

declare(strict_types=1);

namespace Arqel\Fields\Concerns;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Field-level authorization (RF-F-09, RF-AU-03).
 *
 * `canSee()` and `canEdit()` register predicates evaluated by the
 * controller before serialising the field schema or accepting an
 * update payload. The two oracles `canBeSeenBy()` / `canBeEditedBy()`
 * default to `true` when no callback is set, so unannotated fields
 * stay fully accessible.
 *
 * **Enforcement is real**: fields the user cannot see must be
 * stripped from the Inertia payload entirely (not just disabled in
 * the React UI). The controller layer (CORE-006) consumes these
 * oracles to do that pruning server-side.
 */
trait HasAuthorization
{
    protected ?Closure $canSeeCallback = null;

    protected ?Closure $canEditCallback = null;

    public function canSee(Closure $callback): static
    {
        $this->canSeeCallback = $callback;

        return $this;
    }

    public function canEdit(Closure $callback): static
    {
        $this->canEditCallback = $callback;

        return $this;
    }

    public function canBeSeenBy(?Authenticatable $user = null, ?Model $record = null): bool
    {
        if ($this->canSeeCallback === null) {
            return true;
        }

        return (bool) ($this->canSeeCallback)($user, $record);
    }

    public function canBeEditedBy(?Authenticatable $user = null, ?Model $record = null): bool
    {
        if (! $this->canBeSeenBy($user, $record)) {
            return false;
        }

        if ($this->canEditCallback === null) {
            return true;
        }

        return (bool) ($this->canEditCallback)($user, $record);
    }

    public function getCanSeeCallback(): ?Closure
    {
        return $this->canSeeCallback;
    }

    public function getCanEditCallback(): ?Closure
    {
        return $this->canEditCallback;
    }
}
