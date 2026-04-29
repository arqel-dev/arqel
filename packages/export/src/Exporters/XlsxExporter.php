<?php

declare(strict_types=1);

namespace Arqel\Export\Exporters;

use Arqel\Export\Contracts\Exporter;
use DateTimeInterface;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streaming XLSX exporter backed by `spatie/simple-excel` (OpenSpout under the hood).
 *
 * Mirrors {@see CsvExporter} structure, but preserves native cell types
 * where Excel benefits from them — `DateTimeInterface` instances are
 * passed through unchanged so Excel renders them as dates rather than
 * literal strings; numerics flow through as-is.
 *
 * Header styling (bold) and frozen-row support are intentionally omitted:
 * coupling to OpenSpout internals via `setHeaderStyle()` proved fragile
 * (the test suite hit `fwrite(): supplied resource is not a valid stream`
 * on the headerStyle path) and spatie/simple-excel does not yet expose a
 * stable `freezeRow()` helper at v3.x. Plain header is still readable in
 * Excel; bold/frozen ergonomics revisit in a follow-up ticket.
 *
 * TODO(EXPORT-XXX): revisit bold header + frozen-row + auto column widths
 * once spatie/simple-excel adds first-class helpers, or pin a known-good
 * OpenSpout style invocation that survives the testbench environment.
 */
final class XlsxExporter implements Exporter
{
    /**
     * @param iterable<int, mixed> $rows
     * @param array<int, array<string, mixed>> $columns
     */
    public function export(iterable $rows, array $columns, string $destination): string
    {
        $writer = SimpleExcelWriter::create($destination)
            ->addHeader($this->headerLabels($columns));

        foreach ($rows as $record) {
            $writer->addRow($this->buildRow($record, $columns));
        }

        $writer->close();

        return $destination;
    }

    /**
     * Stream an XLSX download directly to the browser without writing
     * to disk first. Use only for small/sync exports — large datasets
     * should go through the async `ExportAction` pipeline (EXPORT-005+).
     *
     * @param iterable<int, mixed> $rows
     * @param array<int, array<string, mixed>> $columns
     */
    public static function streamDownload(iterable $rows, array $columns, string $filename): StreamedResponse
    {
        $exporter = new self;

        return new StreamedResponse(function () use ($exporter, $rows, $columns, $filename): void {
            $writer = SimpleExcelWriter::streamDownload($filename)
                ->addHeader($exporter->headerLabels($columns));

            foreach ($rows as $record) {
                $writer->addRow($exporter->buildRow($record, $columns));
            }

            $writer->close();
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     *
     * @return array<int, string>
     */
    private function headerLabels(array $columns): array
    {
        return array_map(
            static fn (array $col): string => (string) ($col['label'] ?? $col['name'] ?? ''),
            $columns,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     *
     * @return array<int, mixed>
     */
    private function buildRow(mixed $record, array $columns): array
    {
        $row = [];
        foreach ($columns as $column) {
            $row[] = $this->formatCell($record, $column);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $column
     */
    private function formatCell(mixed $record, array $column): mixed
    {
        $type = $column['type'] ?? null;
        $name = (string) ($column['name'] ?? '');

        if ($type === 'relationship') {
            $path = (string) ($column['display_path'] ?? $name);
            $value = data_get($record, $path);

            return $value ?? '';
        }

        $value = data_get($record, $name);

        return match ($type) {
            'date' => $value instanceof DateTimeInterface
                ? $value
                : ($value === null ? '' : (string) $value),
            'boolean' => $value ? 'Yes' : 'No',
            default => $value ?? '',
        };
    }
}
