<?php

declare(strict_types=1);

namespace Arqel\Tenant\Resolvers;

use Illuminate\Contracts\Auth\Authenticatable;
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
 *
 * For tenant switching, the resolver writes the chosen tenant
 * key back to a configurable foreign-key column on the user
 * (default `current_tenant_id`), and enumerates available
 * tenants via a configurable many-to-many style relation
 * (default `tenants`).
 */
class AuthUserResolver extends AbstractTenantResolver
{
    public function __construct(
        string $modelClass,
        string $identifierColumn = 'id',
        private readonly string $relation = 'currentTeam',
        private readonly string $availableRelation = 'tenants',
        private readonly string $foreignKeyColumn = 'current_tenant_id',
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

    protected function switchableRelationName(): string
    {
        return $this->availableRelation;
    }

    protected function switchTargetColumn(): string
    {
        return $this->foreignKeyColumn;
    }

    /**
     * Persist the chosen tenant into the SAME column `resolve()` reads
     * from. `resolve()` reads via the `currentTeam` relation, so a switch
     * must write that relation's foreign key — otherwise the inherited
     * {@see AbstractTenantResolver::switchTo()} wrote `current_tenant_id`,
     * a column the relation never reads, and the switch was silently lost
     * on the next request (mirrors the #81 fix in SessionResolver).
     *
     * When `currentTeam` is a `BelongsTo` its real foreign key is used
     * (Jetstream's `current_team_id`, or any custom key); otherwise we
     * fall back to the configured `foreignKeyColumn`.
     */
    public function switchTo(Authenticatable $user, Model $tenant): void
    {
        if (! $user instanceof Model) {
            return;
        }

        $column = $this->resolveForeignKeyColumn($user);
        $user->{$column} = $tenant->getKey();
        $user->save();
    }

    private function resolveForeignKeyColumn(Model $user): string
    {
        if (method_exists($user, $this->relation)) {
            $relation = $user->{$this->relation}();

            if ($relation instanceof BelongsTo) {
                return $relation->getForeignKeyName();
            }
        }

        return $this->foreignKeyColumn;
    }
}
