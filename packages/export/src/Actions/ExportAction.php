<?php

declare(strict_types=1);

namespace Arqel\Export\Actions;

use Arqel\Actions\Action;
use Arqel\Export\ExportFormat;
use RuntimeException;

/**
 * Pre-configured bulk action that exports the current selection
 * to a chosen format.
 *
 * Note on the base class: the spec for EXPORT-001 lists the parent
 * as `BulkAction`, but `Arqel\Actions\Types\BulkAction` is declared
 * `final`. To respect both the "do not modify other packages" rule
 * and Action's contract, `ExportAction` extends `Action` directly
 * and emits `type = 'bulk'` so consumers (Table toolbar, action
 * resolver) treat it identically. Bulk-specific concerns
 * (chunking, deselect-after) are deferred to EXPORT-005 where the
 * dispatch wiring lands.
 */
final class ExportAction extends Action
{
    protected string $type = 'bulk';

    private ExportFormat $format = ExportFormat::CSV;

    public static function make(string $name): static
    {
        $action = new self($name);
        $action->label('Export');
        $action->icon('download');

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
     * @param array<string, mixed> $data
     */
    public function execute(mixed $record = null, array $data = []): mixed
    {
        throw new RuntimeException('Wired in EXPORT-005');
    }
}
