<?php

declare(strict_types=1);

namespace Arqel\Tenant\Scopes;

use Arqel\Tenant\TenantManager;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope applied by `BelongsToTenant`. When a tenant is
 * resolved (`TenantManager::hasCurrent() === true`), every
 * query is constrained with `where(<tenant_fk>, <id>)` — apps
 * never see cross-tenant data without explicitly opting out.
 *
 * When no tenant is current, the scope is a no-op. Background
 * jobs that need a specific tenant should call
 * `TenantManager::runFor()` to scope the closure.
 *
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $container = Container::getInstance();

        if (! $container->bound(TenantManager::class)) {
            return;
        }

        /** @var TenantManager $manager */
        $manager = $container->make(TenantManager::class);

        if (! $manager->hasCurrent()) {
            return;
        }

        $tenant = $manager->current();
        if ($tenant === null) {
            return;
        }

        if (! method_exists($model, 'getQualifiedTenantKeyName')) {
            return;
        }

        $column = $model->getQualifiedTenantKeyName();
        if (! is_string($column)) {
            return;
        }

        $builder->where($column, $tenant->getKey());
    }
}
