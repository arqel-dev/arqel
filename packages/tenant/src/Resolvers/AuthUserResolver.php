<?php

declare(strict_types=1);

namespace Arqel\Tenant\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Resolves the tenant from the authenticated user's relationship
 * (Jetstream/Spark convention: `currentTeam`).
 *
 * The relation method may either return a `BelongsTo` (preferred)
 * or a `Model` directly — both shapes are accepted so it works
 * with `currentTeam()` *and* eager-loaded `currentTeam` accessors.
 *
 * `modelClass` is still required so `identifierFor()` can be
 * called by the manager even when no user is authenticated.
 */
class AuthUserResolver extends AbstractTenantResolver
{
    public function __construct(
        string $modelClass,
        string $identifierColumn = 'id',
        private readonly string $relation = 'currentTeam',
    ) {
        parent::__construct($modelClass, $identifierColumn);
    }

    public function resolve(Request $request): ?Model
    {
        $user = $request->user();

        if (! $user instanceof Model) {
            return null;
        }

        if (! method_exists($user, $this->relation) && ! isset($user->{$this->relation})) {
            return null;
        }

        $candidate = $this->resolveRelation($user);

        if ($candidate instanceof Model && $candidate instanceof $this->modelClass) {
            return $candidate;
        }

        return null;
    }

    private function resolveRelation(Model $user): ?Model
    {
        if (method_exists($user, $this->relation)) {
            $relation = $user->{$this->relation}();

            if ($relation instanceof BelongsTo) {
                $related = $relation->getResults();

                return $related instanceof Model ? $related : null;
            }

            if ($relation instanceof Model) {
                return $relation;
            }
        }

        $value = $user->{$this->relation} ?? null;

        return $value instanceof Model ? $value : null;
    }
}
