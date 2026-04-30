<?php

declare(strict_types=1);

namespace Arqel\Export\Tests\Fixtures;

use Arqel\Export\Contracts\ExportLogger;
use Arqel\Export\ExportFormat;
use Throwable;

final class RecordingExportLogger implements ExportLogger
{
    /**
     * @var array<int, array{event: string, exportId: string, format: ExportFormat, path?: string, exception?: Throwable}>
     */
    public array $events = [];

    public function logQueued(string $exportId, ExportFormat $format): void
    {
        $this->events[] = ['event' => 'queued', 'exportId' => $exportId, 'format' => $format];
    }

    public function logCompleted(string $exportId, string $path, ExportFormat $format): void
    {
        $this->events[] = [
            'event' => 'completed',
            'exportId' => $exportId,
            'format' => $format,
            'path' => $path,
        ];
    }

    public function logFailed(string $exportId, ExportFormat $format, Throwable $exception): void
    {
        $this->events[] = [
            'event' => 'failed',
            'exportId' => $exportId,
            'format' => $format,
            'exception' => $exception,
        ];
    }
}
