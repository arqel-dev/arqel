<?php

declare(strict_types=1);

namespace Arqel\Tenant\Concerns;

use Arqel\Tenant\Scopes\TenantScope;
use Arqel\Tenant\TenantManager;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Eloquent trait that wires a model to the current tenant:
 *
 *  - applies `TenantScope` global scope so reads always filter
 *    by `<tenant_fk>` automatically;
 *  - on `creating`, auto-fills the foreign key with
 *    `TenantManager::current()->getKey()` when the attribute is
 *    not yet set;
 *  - exposes `tenant()` BelongsTo relation;
 *  - exposes `withoutTenant()` and `forTenant($id)` query
 *    scopes for explicit overrides.
 *
 * Foreign key column resolves from `getTenantForeignKey()`
 * (model-level static) or `config('arqel.tenancy.foreign_key',
 * 'tenant_id')`.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            /** @var Model&object{getTenantKeyName(): string} $model */
            $key = $model->getTenantKeyName();

            if ($model->getAttribute($key) !== null) {
                return;
            }

            $container = Container::getInstance();
            if (! $container->bound(TenantManager::class)) {
                return;
            }

            /** @var TenantManager $manager */
            $manager = $container->make(TenantManager::class);
            $tenant = $manager->current();

            if ($tenant !== null) {
                $model->setAttribute($key, $tenant->getKey());
            }
        });
    }

    public function getTenantKeyName(): string
    {
        if (property_exists($this, 'tenantForeignKey') && is_string($this->tenantForeignKey)) {
            return $this->tenantForeignKey;
        }

        $configured = function_exists('config') ? config('arqel.tenancy.foreign_key', 'tenant_id') : 'tenant_id';

        return is_string($configured) ? $configured : 'tenant_id';
    }

    public function getQualifiedTenantKeyName(): string
    {
        return $this->getTable().'.'.$this->getTenantKeyName();
    }

    public function tenant(): BelongsTo
    {
        $tenantModel = function_exists('config') ? config('arqel.tenancy.model') : null;

        if (! is_string($tenantModel) || ! class_exists($tenantModel)) {
            throw new LogicException(
                'arqel.tenancy.model is not configured — set it before using BelongsToTenant::tenant().',
            );
        }

        return $this->belongsTo($tenantModel, $this->getTenantKeyName());
    }

    /**
     * Query scope: drop the tenant global scope for this query.
     * **Use carefully** — bypassing the scope means reads can
     * span tenants. Reserve for admin/global views or for
     * explicitly-scoped repository methods.
     */
    public function scopeWithoutTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Query scope: drop the global scope and re-filter against
     * a specific tenant id (or Model). Useful for cross-tenant
     * read paths in admin/global views.
     */
    public function scopeForTenant(Builder $query, Model|int|string $tenant): Builder
    {
        $id = $tenant instanceof Model ? $tenant->getKey() : $tenant;

        return $query
            ->withoutGlobalScope(TenantScope::class)
            ->where($this->getTenantKeyName(), $id);
    }
}
