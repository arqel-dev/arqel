<?php

declare(strict_types=1);

namespace Arqel\Export;

use Arqel\Export\Contracts\ExportLogger;
use Arqel\Export\Logging\NullExportLogger;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel/export`.
 *
 * Bindings:
 *   - `ExportLogger` → `NullExportLogger` (singletonIf — apps may override)
 *
 * Routes:
 *   - `routes/admin.php` — registers `GET /admin/exports/{exportId}/download`
 *     under `web + auth`. Consumer apps SHOULD wrap with their own
 *     authorization gate (see `ExportDownloadController` docblock).
 */
final class ExportServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel-export')
            ->hasRoute('admin');
    }

    public function packageRegistered(): void
    {
        $this->app->singletonIf(ExportLogger::class, NullExportLogger::class);
    }
}
