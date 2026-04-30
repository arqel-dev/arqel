<?php

declare(strict_types=1);

namespace Arqel\Export\Jobs;

use Arqel\Export\Contracts\ExportLogger;
use Arqel\Export\Contracts\RecordsResolver;
use Arqel\Export\Exporters\CsvExporter;
use Arqel\Export\Exporters\PdfExporter;
use Arqel\Export\Exporters\XlsxExporter;
use Arqel\Export\ExportFormat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;
use Throwable;

/**
 * Queueable export job — writes the chosen format to disk under
 * `<destinationDir>/export-<exportId>.<ext>`.
 *
 * EXPORT-006 (scoped) constraints:
 * - The job does NOT load records via a Resource model: instead it
 *   delegates to a `RecordsResolver` whose FQCN is stored in the
 *   payload. The resolver is resolved out of the container at
 *   handle-time and asked to produce a (streaming) iterable. This
 *   keeps the queue payload small and decouples the job from the
 *   Resource API + User model.
 * - Lifecycle reporting goes through the `ExportLogger` contract
 *   (default `NullExportLogger`); consumers wire their own
 *   implementation to persist Export rows + dispatch notifications.
 *
 * Cleanup of stale files (>7 days) is deferred to a separate ticket.
 */
final class ProcessExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param array<int, array<string, mixed>> $columns
     * @param class-string<RecordsResolver> $recordsResolverClass
     */
    public function __construct(
        public readonly string $exportId,
        public readonly ExportFormat $format,
        public readonly array $columns,
        public readonly string $recordsResolverClass,
        public readonly ?string $destinationDir = null,
    ) {}

    public function handle(ExportLogger $logger): void
    {
        try {
            /** @var mixed $resolver */
            $resolver = app($this->recordsResolverClass);

            if (! $resolver instanceof RecordsResolver) {
                throw new InvalidArgumentException(sprintf(
                    'Records resolver [%s] must implement %s.',
                    $this->recordsResolverClass,
                    RecordsResolver::class,
                ));
            }

            $records = $resolver->resolve();

            $exporter = match ($this->format) {
                ExportFormat::CSV => new CsvExporter,
                ExportFormat::XLSX => new XlsxExporter,
                ExportFormat::PDF => new PdfExporter,
            };

            $dir = $this->destinationDir ?? storage_path('app/arqel-exports');

            if (! is_dir($dir) && ! @mkdir($dir, 0o755, true) && ! is_dir($dir)) {
                throw new InvalidArgumentException(sprintf(
                    'Unable to create export destination directory [%s].',
                    $dir,
                ));
            }

            $filename = 'export-'.$this->exportId.'.'.$this->format->extension();
            $destination = rtrim($dir, '/').'/'.$filename;

            $exporter->export($records, $this->columns, $destination);

            $logger->logCompleted($this->exportId, $destination, $this->format);
        } catch (Throwable $exception) {
            $logger->logFailed($this->exportId, $this->format, $exception);

            throw $exception;
        }
    }
}
