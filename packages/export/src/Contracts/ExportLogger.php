<?php

declare(strict_types=1);

namespace Arqel\Export\Contracts;

use Arqel\Export\ExportFormat;
use Throwable;

/**
 * Lifecycle hook for export job state.
 *
 * The default implementation is `Arqel\Export\Logging\NullExportLogger`
 * (no-op). Consumer apps that want to persist exports in a DB table
 * (`exports`) and/or notify users wire their own implementation and
 * bind it in their service provider:
 *
 * ```php
 * $this->app->singleton(ExportLogger::class, MyAppExportLogger::class);
 * ```
 *
 * Keeping persistence + notifications out of `arqel-dev/export` itself
 * lets the package stay agnostic of the User model, the Notification
 * pipeline and the Export model schema.
 */
interface ExportLogger
{
    public function logQueued(string $exportId, ExportFormat $format): void;

    public function logCompleted(string $exportId, string $path, ExportFormat $format): void;

    public function logFailed(string $exportId, ExportFormat $format, Throwable $exception): void;
}
