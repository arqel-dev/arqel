<?php

declare(strict_types=1);

namespace Arqel\Export;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel/export`.
 *
 * EXPORT-001 ships only the package skeleton, so this provider is
 * intentionally bare:
 *
 *   - No migrations — the `Export` model + tracking table land in
 *     a later ticket (EXPORT-006+).
 *   - No config — exporter selection is per-call via
 *     `ExportAction::format()`.
 *   - No routes — async dispatch + download endpoint land in
 *     EXPORT-005.
 */
final class ExportServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('arqel-export');
    }
}
