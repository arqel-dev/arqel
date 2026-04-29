<?php

declare(strict_types=1);

namespace Arqel\Tenant\Resolvers;

use Arqel\Tenant\Contracts\TenantResolver;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Shared scaffolding for the built-in resolvers — model class
 * validation, identifier column tracking, and a default
 * `identifierFor()` implementation that falls back to the model
 * key when the configured column is unset.
 */
abstract class AbstractTenantResolver implements TenantResolver
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
}
