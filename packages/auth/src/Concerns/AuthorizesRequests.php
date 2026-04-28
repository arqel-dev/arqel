<?php

declare(strict_types=1);

namespace Arqel\Auth\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Convenience oracles for Arqel controllers (and tests). Each
 * helper aborts with 403 when the active user fails the gate, or
 * returns silently otherwise. Use `denies` / `allows` directly when
 * you want to branch instead of aborting.
 *
 * The trait is duck-typed against external collaborators:
 *   - Resources expose `getModel(): class-string`
 *   - Actions expose `getName(): string` + `canBeExecutedBy(...)`
 *   - Fields expose `getName(): string` + a `canBeSeenBy/EditedBy`
 *     pair (when `arqel/fields` HasAuthorization is mixed in).
 */
trait AuthorizesRequests
{
    /**
     * Authorise a Resource-level action (`viewAny`/`create`/`view`/
     * `update`/`delete`) for the active user. Aborts with 403 when
     * the gate denies; silently allows when no policy is registered
     * for the model (Resource Policies are user-owned).
     *
     * @param class-string $resourceClass
     */
    protected function authorizeResource(string $resourceClass, string $action, mixed $record = null): void
    {
        if (! method_exists($resourceClass, 'getModel')) {
            return;
        }

        $modelClass = $resourceClass::getModel();
        if (! is_string($modelClass)) {
            return;
        }
        $argument = $record ?? $modelClass;

        if (! Gate::has($action) && ! Gate::getPolicyFor($modelClass)) {
            return;
        }

        if (Gate::denies($action, $argument)) {
            abort(HttpResponse::HTTP_FORBIDDEN);
        }
    }

    /**
     * Authorise an Action invocation. Delegates to the Action's own
     * `canBeExecutedBy` Closure (default: always allow).
     */
    protected function authorizeAction(object $action, mixed $record = null): void
    {
        if (! method_exists($action, 'canBeExecutedBy')) {
            return;
        }

        $user = Auth::user();

        if (! $action->canBeExecutedBy($user, $record)) {
            abort(HttpResponse::HTTP_FORBIDDEN);
        }
    }

    /**
     * Authorise a per-field operation (`view`/`edit`). Honours the
     * `HasAuthorization` trait from `arqel/fields` when present.
     * Aborts with 403 when the field's gate denies.
     */
    protected function authorizeField(object $field, string $operation, mixed $record = null): void
    {
        $user = Auth::user();

        if ($operation === 'edit' && method_exists($field, 'canBeEditedBy')) {
            if (! $field->canBeEditedBy($user, $record)) {
                abort(HttpResponse::HTTP_FORBIDDEN);
            }

            return;
        }

        if (method_exists($field, 'canBeSeenBy')) {
            if (! $field->canBeSeenBy($user, $record)) {
                abort(HttpResponse::HTTP_FORBIDDEN);
            }
        }
    }
}
