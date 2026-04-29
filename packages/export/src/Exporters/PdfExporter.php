<?php

declare(strict_types=1);

namespace Arqel\Export\Exporters;

use Arqel\Export\Contracts\Exporter;
use RuntimeException;

/**
 * PDF exporter stub. The real implementation backed by
 * `dompdf/dompdf` lands in EXPORT-004.
 */
final class PdfExporter implements Exporter
{
    public function export(iterable $rows, array $columns, string $destination): string
    {
        throw new RuntimeException('Not implemented in EXPORT-001 — see EXPORT-002/003/004');
    }
}
