<?php

declare(strict_types=1);

namespace Arqel\Export\Exporters;

use Arqel\Export\Contracts\Exporter;
use DateTimeInterface;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streaming CSV exporter backed by `spatie/simple-excel`.
 *
 * The file-based path (`export()`) writes to an absolute destination
 * supplied by the caller (typically a queued job persisting to a disk).
 * The HTTP path (`streamDownload()`) is a thin convenience for sync
 * downloads from a controller — both share the same column-formatting
 * logic so output stays consistent.
 *
 * UTF-8 BOM is enabled by default by SimpleExcelWriter so Excel-on-Windows
 * opens the file with the correct encoding.
 */
final class CsvExporter implements Exporter
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
     * Stream a CSV download directly to the browser without writing
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
            'Content-Type' => 'text/csv; charset=UTF-8',
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
     * @return array<int, string>
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
    private function formatCell(mixed $record, array $column): string
    {
        $type = $column['type'] ?? null;
        $name = (string) ($column['name'] ?? '');

        if ($type === 'relationship') {
            $path = (string) ($column['display_path'] ?? $name);
            $value = data_get($record, $path);

            return $value === null ? '' : (string) $value;
        }

        $value = data_get($record, $name);

        return match ($type) {
            'date' => $value instanceof DateTimeInterface
                ? $value->format('Y-m-d')
                : ($value === null ? '' : (string) $value),
            'boolean' => $value ? 'Yes' : 'No',
            default => $value === null ? '' : (string) $value,
        };
    }
}
