<?php

declare(strict_types=1);

namespace Arqel\Import\Logging;

use Arqel\Import\Contracts\ImportLogger;
use Arqel\Import\ImportFormat;
use Throwable;

final class NullImportLogger implements ImportLogger
{
    public function logQueued(string $importId, ImportFormat $format): void
    {
        // no-op
    }

    public function progress(string $importId, int $imported, int $skipped): void
    {
        // no-op
    }

    public function logCompleted(string $importId, int $imported, int $skipped, ?string $failedRowsPath): void
    {
        // no-op
    }

    public function logFailed(string $importId, ImportFormat $format, Throwable $exception): void
    {
        // no-op
    }
}
