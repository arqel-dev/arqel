<?php

declare(strict_types=1);

namespace Arqel\Export\Exporters;

use Arqel\Export\Contracts\Exporter;
use Closure;
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
    use FormatsDateCells;

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
        $safeName = self::sanitizeFilename($filename);

        return new StreamedResponse(function () use ($exporter, $rows, $columns, $safeName): void {
            $writer = SimpleExcelWriter::streamDownload($safeName)
                ->addHeader($exporter->headerLabels($columns));

            foreach ($rows as $record) {
                $writer->addRow($exporter->buildRow($record, $columns));
            }

            $writer->close();
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $safeName),
        ]);
    }

    /**
     * Strip characters that could break out of the quoted `filename="..."`
     * header value — double quotes and CR/LF (header injection / response
     * splitting) — plus path separators, so an attacker-influenced export
     * name cannot forge headers. Falls back to a safe default if nothing
     * usable remains.
     */
    private static function sanitizeFilename(string $filename): string
    {
        $clean = preg_replace('/[\x00-\x1f"\\\\\/]+/', '', $filename) ?? '';
        $clean = trim($clean);

        return $clean === '' ? 'export.csv' : $clean;
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
        return $this->sanitizeForCsv($this->resolveCellValue($record, $column));
    }

    /**
     * @param array<string, mixed> $column
     */
    private function resolveCellValue(mixed $record, array $column): string
    {
        $type = $column['type'] ?? null;
        $name = (string) ($column['name'] ?? '');

        // Column state pipeline (#206): when the producer attached a
        // `state_resolver` Closure (getStateUsing/formatStateUsing — the
        // engine behind ComputedColumn), it owns the cell value, so a
        // computed/formatted cell exports its resolved value instead of a
        // blank/raw `data_get`. The resolved value still flows through the
        // type-formatting match below so date/boolean stay consistent.
        $resolver = $column['state_resolver'] ?? null;
        if ($resolver instanceof Closure) {
            $value = $resolver($record);

            return match ($type) {
                'date' => $value instanceof DateTimeInterface
                    ? $this->formatDateCell($value, $column)
                    : ($value === null ? '' : (string) $value),
                'boolean' => $this->formatBooleanCell($value),
                default => $value === null ? '' : (string) $value,
            };
        }

        if ($type === 'relationship') {
            $path = (string) ($column['display_path'] ?? $name);
            $value = data_get($record, $path);

            return $value === null ? '' : (string) $value;
        }

        $value = data_get($record, $name);

        return match ($type) {
            'date' => $value instanceof DateTimeInterface
                ? $this->formatDateCell($value, $column)
                : ($value === null ? '' : (string) $value),
            'boolean' => $this->formatBooleanCell($value),
            default => $value === null ? '' : (string) $value,
        };
    }

    /**
     * Neutralize CSV formula injection: a cell whose first character is one of
     * `= + - @` or a control char (tab, CR, LF) is interpreted as a formula by
     * Excel/Sheets. Prefixing an apostrophe forces the spreadsheet to treat the
     * value as literal text. Erring toward over-escaping (e.g. a bare "-5")
     * is deliberate — leaking a live formula is the worse failure. See OWASP
     * "CSV Injection".
     */
    private function sanitizeForCsv(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        return match ($value[0]) {
            '=', '+', '-', '@', "\t", "\r", "\n" => "'".$value,
            default => $value,
        };
    }
}
