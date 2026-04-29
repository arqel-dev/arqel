<?php

declare(strict_types=1);

namespace Arqel\Audit;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel/audit`.
 *
 * AUDIT-001 ships the package skeleton only:
 *
 * - The Spatie ActivityLog provider (auto-discovered from
 *   `spatie/laravel-activitylog`) is responsible for the `activity_log`
 *   migration + `Activity` model bindings — we deliberately do not
 *   re-register them here.
 * - Routes for `ActivityLogController` are intentionally NOT registered
 *   in this scaffold; AUDIT-002+ wires them up alongside the timeline
 *   Inertia views.
 */
final class AuditServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel-audit')
            ->hasRoute('admin');
    }
}
