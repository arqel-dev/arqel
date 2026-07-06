<?php

declare(strict_types=1);

namespace Arqel\Ai\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Gate;

/**
 * Shared authorization for the AI field endpoints.
 *
 * The global `use-ai` gate is an opt-in coarse switch (allow-by-default
 * when unregistered). It does NOT replace per-resource authorization —
 * without this, any authenticated user could invoke AI on a Resource whose
 * Policy would otherwise deny them. This mirrors the scaffold-safe pattern
 * of `Arqel\Core\Http\Controllers\ResourceController::authorize()`: a
 * registered gate/Policy is enforced, but "Hello World" apps with neither
 * are left permissive so the endpoints work out of the box.
 */
trait AuthorizesAiRequest
{
    /**
     * @return bool `true` when the request is allowed to proceed.
     */
    private function passesUseAiGate(): bool
    {
        return ! (Gate::has('use-ai') && ! Gate::allows('use-ai'));
    }

    /**
     * Enforce the Resource's Policy for the given model class, scaffold-safe.
     * Returns `true` when allowed; `false` when a registered gate/Policy
     * denies the `viewAny` ability for the resolved model.
     *
     * @param class-string $modelClass
     */
    private function authorizesResource(string $modelClass): bool
    {
        if (! Gate::has('viewAny') && ! Gate::getPolicyFor($modelClass)) {
            return true;
        }

        return Gate::allows('viewAny', $modelClass);
    }
}
