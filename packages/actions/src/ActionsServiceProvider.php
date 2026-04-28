<?php

declare(strict_types=1);

namespace Arqel\Actions;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for `arqel/actions`.
 *
 * Boot anchor only. The `Action` base class, concrete types
 * (RowAction/BulkAction/ToolbarAction/HeaderAction), concerns
 * (Confirmable/HasForm/HasAuthorization/HasQueuing), and the
 * `ActionExecutor` + HTTP controller arrive in ACTIONS-002+.
 */
final class ActionsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('arqel-actions');
    }
}
