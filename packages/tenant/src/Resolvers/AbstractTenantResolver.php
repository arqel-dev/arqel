<?php

declare(strict_types=1);

namespace Arqel\Tenant\Resolvers;

use Arqel\Tenant\Contracts\SupportsTenantSwitching;
use Arqel\Tenant\Contracts\TenantResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Shared scaffolding for the built-in resolvers — model class
 * validation, identifier column tracking, and a default
 * `identifierFor()` implementation that falls back to the model
 * key when the configured column is unset.
 *
 * Also implements `SupportsTenantSwitching` with a sensible default
 * suitable for the AuthUserResolver flow: tenants come from a
 * `tenants` relationship on the user, and switching writes back
 * to a configurable column (default `current_tenant_id`).
 */
abstract class AbstractTenantResolver implements SupportsTenantSwitching, TenantResolver
{
    /**
     * @param class-string<Model> $modelClass The Eloquent class
     *                                        backing the tenant identity.
     * @param string $identifierColumn Column queried when
     *                                 resolving from the request and
     *                                 also used by `identifierFor()`.
     */
    public function __construct(
        protected readonly string $modelClass,
        protected readonly string $identifierColumn = 'id',
    ) {
        if (! is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException(sprintf(
                '[%s] expects a class-string of Eloquent\\Model, got [%s].',
                static::class,
                $modelClass,
            ));
        }
    }

    public function identifierFor(Model $tenant): string
    {
        $value = $tenant->getAttribute($this->identifierColumn);

        if (is_scalar($value)) {
            return (string) $value;
        }

        $key = $tenant->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    /**
     * Resolve a tenant by querying `$identifierColumn` against the
     * given value. Returns `null` when no row matches.
     */
    protected function findByIdentifier(string $value): ?Model
    {
        if ($value === '') {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $this->modelClass;

        return $modelClass::query()
            ->where($this->identifierColumn, $value)
            ->first();
    }

    /**
     * @return array<int, Model>
     */
    public function availableFor(Authenticatable $user): array
    {
        if (! $user instanceof Model) {
            return [];
        }

        $relation = $this->switchableRelationName();

        if (! method_exists($user, $relation) && ! isset($user->{$relation})) {
            return [];
        }

        $value = method_exists($user, $relation)
            ? $user->{$relation}()
            : $user->{$relation};

        if ($value instanceof BelongsToMany || $value instanceof HasMany) {
            $value = $value->getResults();
        }

        if ($value instanceof Collection) {
            /** @var array<int, Model> $items */
            $items = array_values(array_filter(
                $value->all(),
                fn ($item): bool => $item instanceof $this->modelClass,
            ));

            return $items;
        }

        if (is_array($value)) {
            return array_values(array_filter(
                $value,
                fn ($item): bool => $item instanceof $this->modelClass,
            ));
        }

        return [];
    }

    public function canSwitchTo(Authenticatable $user, Model $tenant): bool
    {
        if (! $tenant instanceof $this->modelClass) {
            return false;
        }

        $tenantKey = $tenant->getKey();

        foreach ($this->availableFor($user) as $candidate) {
            if ($candidate->getKey() === $tenantKey) {
                return true;
            }
        }

        return false;
    }

    public function switchTo(Authenticatable $user, Model $tenant): void
    {
        if (! $user instanceof Model) {
            return;
        }

        $column = $this->switchTargetColumn();
        $user->{$column} = $tenant->getKey();
        $user->save();
    }

    /**
     * Relation name on the user that lists tenants the user may
     * access. Override per-resolver when the convention differs
     * (e.g. `teams` for Jetstream, `organizations` for Spark).
     */
    protected function switchableRelationName(): string
    {
        return 'tenants';
    }

    /**
     * Column on the user table that stores the persisted
     * "current tenant" key. Override to align with Jetstream's
     * `current_team_id`, etc.
     */
    protected function switchTargetColumn(): string
    {
        return 'current_tenant_id';
    }
}
