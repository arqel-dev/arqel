<?php

declare(strict_types=1);

namespace Arqel\Export;

/**
 * Export output formats supported by Arqel.
 *
 * Each case maps to a concrete `Exporter` implementation in
 * `Arqel\Export\Exporters\*`. Mime type / extension helpers are
 * here so callers (HTTP responses, filename builders, the future
 * `Export` model) have a single source of truth.
 */
enum ExportFormat: string
{
    case CSV = 'csv';
    case XLSX = 'xlsx';
    case PDF = 'pdf';

    /**
     * IANA mime type used in `Content-Type` headers and the
     * `Export` model's `mime_type` column (lands in EXPORT-006+).
     */
    public function mimeType(): string
    {
        return match ($this) {
            self::CSV => 'text/csv',
            self::XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::PDF => 'application/pdf',
        };
    }

    /**
     * File extension (without leading dot) for the format.
     */
    public function extension(): string
    {
        return $this->value;
    }
}
