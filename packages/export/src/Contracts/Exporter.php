<?php

declare(strict_types=1);

namespace Arqel\Export\Contracts;

/**
 * Format-agnostic exporter contract.
 *
 * Implementations are stateless: they receive the data + column
 * descriptors + a destination path, write the file, and return
 * the path that was written. Real bodies land in:
 *
 *   - `CsvExporter`  → EXPORT-002 (spatie/simple-excel streaming)
 *   - `XlsxExporter` → EXPORT-003 (spatie/simple-excel)
 *   - `PdfExporter`  → EXPORT-004 (dompdf)
 */
interface Exporter
{
    /**
     * @param iterable<int, mixed> $rows Source rows (Eloquent collection, lazy collection, generator).
     * @param array<int, array<string, mixed>> $columns Column descriptors (`name`, `label`, `type`, ...).
     * @param string $destination Absolute path where the file should be written.
     *
     * @return string Absolute path of the written file (typically `$destination`).
     */
    public function export(iterable $rows, array $columns, string $destination): string;
}
