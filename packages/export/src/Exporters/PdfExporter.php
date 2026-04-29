<?php

declare(strict_types=1);

namespace Arqel\Export\Exporters;

use Arqel\Export\Contracts\Exporter;
use DateTimeInterface;
use Dompdf\Dompdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PDF exporter backed by `dompdf/dompdf`.
 *
 * Renders rows as a simple HTML `<table>` and pipes through dompdf to
 * produce the PDF bytes. The default template is intentionally minimal
 * (no Blade) so this ticket carries no Laravel view dependency — Blade
 * integration via `Resource::pdfView()` is the next ticket (EXPORT-005).
 *
 * Orientation and paper size are configurable through fluent setters
 * (`setOrientation()`, `setPaperSize()`) and applied at render time.
 *
 * Cell formatting mirrors {@see CsvExporter::formatCell()} — every cell
 * is stringified before being emitted so the HTML stays predictable.
 */
final class PdfExporter implements Exporter
{
    private string $orientation = 'portrait';

    private string $paperSize = 'a4';

    /**
     * @param iterable<int, mixed> $rows
     * @param array<int, array<string, mixed>> $columns
     */
    public function export(iterable $rows, array $columns, string $destination): string
    {
        $html = $this->renderHtml($rows, $columns);

        $dompdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($this->paperSize, $this->orientation);
        $dompdf->render();

        file_put_contents($destination, (string) $dompdf->output());

        return $destination;
    }

    /**
     * Stream a PDF download directly to the browser without writing to
     * disk first. Use only for small/sync exports — large datasets
     * should go through the async `ExportAction` pipeline (EXPORT-005+).
     *
     * @param iterable<int, mixed> $rows
     * @param array<int, array<string, mixed>> $columns
     */
    public static function streamDownload(iterable $rows, array $columns, string $filename): StreamedResponse
    {
        $exporter = new self;
        $html = $exporter->renderHtml($rows, $columns);

        $dompdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($exporter->paperSize, $exporter->orientation);
        $dompdf->render();

        $output = (string) $dompdf->output();

        return new StreamedResponse(function () use ($output): void {
            echo $output;
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    public function setOrientation(string $orientation): static
    {
        $this->orientation = $orientation;

        return $this;
    }

    public function setPaperSize(string $size): static
    {
        $this->paperSize = $size;

        return $this;
    }

    /**
     * @param iterable<int, mixed> $rows
     * @param array<int, array<string, mixed>> $columns
     */
    private function renderHtml(iterable $rows, array $columns): string
    {
        $headerCells = '';
        foreach ($columns as $column) {
            $label = (string) ($column['label'] ?? $column['name'] ?? '');
            $headerCells .= '<th>'.htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</th>';
        }

        $bodyRows = '';
        foreach ($rows as $record) {
            $bodyRows .= '<tr>';
            foreach ($columns as $column) {
                $cell = $this->formatCell($record, $column);
                $bodyRows .= '<td>'.htmlspecialchars($cell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</td>';
            }
            $bodyRows .= '</tr>';
        }

        $title = htmlspecialchars('Export', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!DOCTYPE html>'
            .'<html><head><meta charset="utf-8">'
            .'<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}'
            .'table{width:100%;border-collapse:collapse}'
            .'th{background:#eee;text-align:left;padding:4px;border:1px solid #ccc}'
            .'td{padding:4px;border:1px solid #ccc}'
            .'h1{font-size:14px}</style></head>'
            .'<body><h1>'.$title.'</h1>'
            .'<table><thead><tr>'.$headerCells.'</tr></thead>'
            .'<tbody>'.$bodyRows.'</tbody></table>'
            .'</body></html>';
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
