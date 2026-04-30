<?php

declare(strict_types=1);

namespace Arqel\Tenant\Theming;

use Arqel\Tenant\TenantManager;

/**
 * Bridges `TenantManager::current()` to a `TenantTheme` value-object.
 *
 * Consumers call `app(TenantThemeResolver::class)->resolve()->toArray()`
 * inside their `HandleInertiaRequests::share()` to surface the active
 * tenant's theme to the React side. Bound as a singleton in
 * `TenantServiceProvider::packageRegistered()`.
 */
final class TenantThemeResolver
{
    public function __construct(
        private readonly TenantManager $manager,
    ) {}

    public function resolve(): TenantTheme
    {
        return TenantTheme::fromTenant($this->manager->current());
    }
}
