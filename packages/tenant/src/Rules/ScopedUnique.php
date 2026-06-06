<?php

declare(strict_types=1);

namespace Arqel\Tenant\Rules;

use Arqel\Tenant\TenantManager;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\ConnectionResolverInterface;
use Throwable;

/**
 * Validation rule asserting that `<column>` is unique inside the
 * current tenant. Single-DB equivalent of Laravel's stock
 * `unique` rule with an automatic tenant filter.
 *
 *   Field::text('slug')
 *       ->rule(new ScopedUnique('posts', 'slug', ignore: $record?->id));
 *
 *   // Field shortcut (lands in TENANT-006 follow-up):
 *   Field::text('slug')->uniqueInTenant('posts', 'slug');
 *
 * Behaviour:
 *   - When a tenant is current, adds `where(<tenant_fk>, <id>)`
 *     to the duplicate query.
 *   - When no tenant is current, falls back to a global unique
 *     check (graceful — same as Laravel's `unique`).
 *   - When `ignore` is supplied, the query excludes that primary
 *     key value (allows updates to keep their own slug).
 *   - When the configured tenant FK column does not exist on the
 *     target table, the tenant filter is skipped (resilient to
 *     misconfiguration).
 *
 * Multi-DB tenancy (stancl/tenancy, spatie/laravel-multitenancy)
 * already isolates queries at the connection level, so this rule
 * is mainly for single-DB row-level scoping.
 */
final class ScopedUnique implements ValidationRule
{
    public function __construct(
        private readonly string $table,
        private readonly string $column,
        private readonly mixed $ignore = null,
        private readonly string $ignoreColumn = 'id',
        private readonly ?string $tenantForeignKey = null,
        private readonly ?string $connection = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $container = Container::getInstance();

        if (! $container->bound(ConnectionResolverInterface::class) && ! $container->bound('db')) {
            return; // No DB facade — defer to other rules.
        }

        /** @var ConnectionResolverInterface $resolver */
        $resolver = $container->bound(ConnectionResolverInterface::class)
            ? $container->make(ConnectionResolverInterface::class)
            : $container->make('db');

        $connection = $resolver->connection($this->connection);
        $query = $connection->table($this->table)->where($this->column, $value);

        if ($this->ignore !== null) {
            $query->where($this->ignoreColumn, '!=', $this->ignore);
        }

        $tenantKey = $this->tenantForeignKey ?? $this->resolveDefaultTenantKey();

        if ($tenantKey !== null && $container->bound(TenantManager::class)) {
            /** @var TenantManager $manager */
            $manager = $container->make(TenantManager::class);
            $tenant = $manager->current();

            // Skip the tenant filter when the FK column is absent from the
            // target table — graceful global-unique fallback, as documented.
            // Without this guard MySQL/Postgres raise "Unknown column".
            if ($tenant !== null && $this->tableHasColumn($connection, $tenantKey)) {
                $query->where($tenantKey, $tenant->getKey());
            }
        }

        if ($query->exists()) {
            $message = function_exists('trans')
                ? trans('validation.unique', ['attribute' => $attribute])
                : sprintf('The %s has already been taken.', $attribute);

            $fail(is_string($message) ? $message : sprintf('The %s has already been taken.', $attribute));
        }
    }

    /**
     * Whether the target table carries the given column. Used to decide
     * if the tenant filter can be applied. Defensive: any failure to
     * introspect the schema (driver quirk, stub connection) is treated
     * as "column present" so existing tenant-scoped behaviour is kept.
     */
    private function tableHasColumn(object $connection, string $column): bool
    {
        if (! method_exists($connection, 'getSchemaBuilder')) {
            return true;
        }

        try {
            $schema = $connection->getSchemaBuilder();

            if (! is_object($schema) || ! method_exists($schema, 'hasColumn')) {
                return true;
            }

            return (bool) $schema->hasColumn($this->table, $column);
        } catch (Throwable) {
            return true;
        }
    }

    private function resolveDefaultTenantKey(): ?string
    {
        if (! function_exists('config')) {
            return 'tenant_id';
        }

        $configured = config('arqel.tenancy.foreign_key', 'tenant_id');

        return is_string($configured) && $configured !== '' ? $configured : null;
    }
}
