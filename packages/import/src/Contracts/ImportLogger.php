<?php

declare(strict_types=1);

namespace Arqel\Import\Contracts;

use Arqel\Import\ImportFormat;
use Throwable;

/**
 * Lifecycle + progress hook for import jobs.
 *
 * Default is {@see \Arqel\Import\Logging\NullImportLogger} (no-op).
 * Apps persist an `imports` row and/or notify users by binding their
 * own implementation in a service provider.
 */
interface ImportLogger
{
    public function logQueued(string $importId, ImportFormat $format): void;

    public function progress(string $importId, int $imported, int $skipped): void;

    public function logCompleted(string $importId, int $imported, int $skipped, ?string $failedRowsPath): void;

    public function logFailed(string $importId, ImportFormat $format, Throwable $exception): void;
}
