<?php

declare(strict_types=1);

namespace Arqel\Export\Exporters;

use Arqel\Export\Contracts\Exporter;
use RuntimeException;

/**
 * XLSX exporter stub. The real implementation backed by
 * `spatie/simple-excel` lands in EXPORT-003.
 */
final class XlsxExporter implements Exporter
{
    public function export(iterable $rows, array $columns, string $destination): string
    {
        throw new RuntimeException('Not implemented in EXPORT-001 — see EXPORT-002/003/004');
    }
}
