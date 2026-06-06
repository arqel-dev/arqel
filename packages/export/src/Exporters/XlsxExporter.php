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
 * Mirrors {@see CsvExporter}/{@see PdfExporter} cell formatting: `date`
 * columns are formatted to a `Y-m-d` string (see {@see self::formatCell()}).
 * Passing a raw `DateTimeInterface` through would make OpenSpout write the
 * Excel serial value (e.g. 46141.4375) under the General number format with
 * no date numFmt attached, so Excel/LibreOffice would display the literal
 * number rather than a date (issue #106). Formatting to a string trades
 * Excel's native date typing for a cell that reads correctly everywhere and
 * stays consistent with the other exporters; numerics flow through as-is.
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
                ? $value->format('Y-m-d')
                : ($value === null ? '' : (string) $value),
            'boolean' => $value ? 'Yes' : 'No',
            default => $value ?? '',
        };
    }
}
