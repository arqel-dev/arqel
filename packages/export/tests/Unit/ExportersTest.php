<?php

declare(strict_types=1);

use Arqel\Export\Contracts\Exporter;
use Arqel\Export\Exporters\CsvExporter;
use Arqel\Export\Exporters\PdfExporter;
use Arqel\Export\Exporters\XlsxExporter;

it('implements the Exporter contract for all 3 formats', function (): void {
    expect(new CsvExporter)->toBeInstanceOf(Exporter::class);
    expect(new XlsxExporter)->toBeInstanceOf(Exporter::class);
    expect(new PdfExporter)->toBeInstanceOf(Exporter::class);
});

it('throws RuntimeException for the PDF stub', function (): void {
    (new PdfExporter)->export([], [], '/tmp/x.pdf');
})->throws(RuntimeException::class, 'Not implemented in EXPORT-001');
