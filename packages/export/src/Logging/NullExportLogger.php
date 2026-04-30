<?php

declare(strict_types=1);

namespace Arqel\Export\Logging;

use Arqel\Export\Contracts\ExportLogger;
use Arqel\Export\ExportFormat;
use Throwable;

/**
 * Default no-op `ExportLogger`.
 *
 * Bound via `singletonIf` in `ExportServiceProvider` so consumer apps
 * can override with their own implementation that persists to a DB
 * table and/or dispatches notifications.
 */
final class NullExportLogger implements ExportLogger
{
    public function logQueued(string $exportId, ExportFormat $format): void
    {
        // no-op
    }

    public function logCompleted(string $exportId, string $path, ExportFormat $format): void
    {
        // no-op
    }

    public function logFailed(string $exportId, ExportFormat $format, Throwable $exception): void
    {
        // no-op
    }
}
