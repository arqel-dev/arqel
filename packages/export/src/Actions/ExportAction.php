<?php

declare(strict_types=1);

namespace Arqel\Export\Actions;

use Arqel\Actions\Action;
use Arqel\Export\Contracts\Exporter;
use Arqel\Export\Exporters\CsvExporter;
use Arqel\Export\Exporters\PdfExporter;
use Arqel\Export\Exporters\XlsxExporter;
use Arqel\Export\ExportFormat;
use InvalidArgumentException;
use Traversable;

/**
 * Pre-configured bulk action that exports the current selection
 * to a chosen format.
 *
 * Note on the base class: the spec for EXPORT-001 lists the parent
 * as `BulkAction`, but `Arqel\Actions\Types\BulkAction` is declared
 * `final`. To respect both the "do not modify other packages" rule
 * and Action's contract, `ExportAction` extends `Action` directly
 * and emits `type = 'bulk'` so consumers (Table toolbar, action
 * resolver) treat it identically.
 *
 * EXPORT-005 (this ticket, scope-narrowed) wires `execute()` to call
 * the right `Exporter` and write the file synchronously, returning a
 * payload describing the produced artifact.
 *
 * TODO(EXPORT-006): the original EXPORT-005 spec also covers (a) a
 * form-modal step to let the user pick the format/columns at runtime,
 * (b) a queue-threshold heuristic that dispatches `ProcessExportJob`
 * for large selections, and (c) a flash notification + signed download
 * URL. All three pieces require cross-package work into
 * `arqel/actions` form integration plus the `Export` model + jobs
 * (EXPORT-006/007/008). They are deliberately deferred — this action
 * is currently a synchronous, in-process exporter wrapper.
 */
final class ExportAction extends Action
{
    protected string $type = 'bulk';

    private ExportFormat $format = ExportFormat::CSV;

    /** @var array<int, array<string, mixed>> */
    private array $columns = [];

    private string $destinationDir;

    private bool $dryRun = false;

    public static function make(string $name): static
    {
        $action = new self($name);
        $action->label('Export');
        $action->icon('download');
        $action->destinationDir = sys_get_temp_dir();

        return $action;
    }

    public function format(ExportFormat $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getFormat(): ExportFormat
    {
        return $this->format;
    }

    /**
     * Configure the column descriptors handed to the exporter.
     *
     * @param array<int, array<string, mixed>> $columns
     */
    public function withColumns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Override the destination directory for the produced file.
     */
    public function withDestinationDir(string $dir): self
    {
        $this->destinationDir = $dir;

        return $this;
    }

    /**
     * When enabled, `execute()` skips the actual exporter call and
     * returns a payload with `path => 'dry-run'`. Useful for unit
     * tests and previewing without I/O.
     */
    public function dryRun(bool $enabled = true): self
    {
        $this->dryRun = $enabled;

        return $this;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{path: string, filename: string, format: string, mimeType: string}
     */
    public function execute(mixed $record = null, array $data = []): mixed
    {
        if (! ($record instanceof Traversable) && ! is_array($record)) {
            throw new InvalidArgumentException('ExportAction::execute expects an iterable of records');
        }

        $filename = 'export-'.date('Ymd-His').'.'.$this->format->extension();
        $destination = rtrim($this->destinationDir, '/').'/'.$filename;

        if (! $this->dryRun) {
            $this->resolveExporter()->export($record, $this->columns, $destination);
        }

        return [
            'path' => $this->dryRun ? 'dry-run' : $destination,
            'filename' => $filename,
            'format' => $this->format->value,
            'mimeType' => $this->format->mimeType(),
        ];
    }

    private function resolveExporter(): Exporter
    {
        return match ($this->format) {
            ExportFormat::CSV => new CsvExporter,
            ExportFormat::XLSX => new XlsxExporter,
            ExportFormat::PDF => new PdfExporter,
        };
    }
}
