<?php

declare(strict_types=1);

namespace Arqel\Auth;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Convenience facade over Laravel's Gate that integrates with the
 * `AbilityRegistry`. `check`/`allows`/`denies` accept the same
 * arguments as Laravel's Gate; `register` is a one-liner over
 * `AbilityRegistry::registerComputed`.
 *
 * Use this for ad-hoc panel-level checks; per-record authorization
 * should still go through Resource Policies.
 */
final class ArqelGate
{
    public function __construct(
        protected AbilityRegistry $registry,
    ) {}

    public function register(string $ability, Closure $callback): self
    {
        $this->registry->registerComputed($ability, $callback);

        return $this;
    }

    public function abilities(string ...$abilities): self
    {
        $this->registry->registerGlobals(array_values($abilities));

        return $this;
    }

    public function allows(string $ability, mixed $arguments = []): bool
    {
        return Gate::forUser($this->user())->allows($ability, $arguments);
    }

    public function denies(string $ability, mixed $arguments = []): bool
    {
        return ! $this->allows($ability, $arguments);
    }

    /**
     * @return array<string, bool>
     */
    public function snapshot(): array
    {
        return $this->registry->resolveForUser($this->user());
    }

    private function user(): ?Authenticatable
    {
        return Auth::user();
    }
}
