<?php

declare(strict_types=1);

namespace Arqel\Actions\Concerns;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Per-action authorization. `authorize()` accepts a closure that
 * receives `(?Authenticatable $user, mixed $record = null)` and
 * returns bool. Default: always authorized — Action policies on
 * the Resource side own the strict gate.
 */
trait HasAuthorization
{
    protected ?Closure $authorize = null;

    public function authorize(Closure $callback): static
    {
        $this->authorize = $callback;

        return $this;
    }

    public function canBeExecutedBy(?Authenticatable $user, mixed $record = null): bool
    {
        if ($this->authorize === null) {
            return true;
        }

        return (bool) ($this->authorize)($user, $record);
    }
}
