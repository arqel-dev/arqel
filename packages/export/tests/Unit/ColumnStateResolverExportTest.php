<?php

declare(strict_types=1);

use Arqel\Export\Exporters\CsvExporter;
use Arqel\Export\Exporters\PdfExporter;
use Arqel\Export\Exporters\XlsxExporter;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * #206: the exporters resolved every cell via `data_get($record, $name)`,
 * bypassing `Column::getState()`/`formatState()`. A `ComputedColumn`
 * (no backing attribute) exported BLANK; a `formatStateUsing` column
 * exported its RAW value.
 *
 * The fix lets a column descriptor carry an optional `state_resolver`
 * Closure — `fn ($record) => $column->formatState($column->getState($record), $record)`
 * — which `ResourceController::serializeColumns` attaches for any column
 * whose `usesStateResolver()` is true. When present, the exporter calls
 * it instead of `data_get`, so the computed/formatted value lands in the
 * file. Columns without a resolver keep their existing `data_get` path
 * (no regression to date/boolean/relationship formatting).
 */
beforeEach(function (): void {
    $this->tempFile = tempnam(sys_get_temp_dir(), 'arqel-state-');
    @unlink($this->tempFile);
    $this->tempFile .= '.csv';
});

afterEach(function (): void {
    if (isset($this->tempFile) && is_string($this->tempFile) && file_exists($this->tempFile)) {
        @unlink($this->tempFile);
    }
});

/**
 * @return array<int, array<int, string>>
 */
function readStateCsvRows(string $path): array
{
    $contents = (string) file_get_contents($path);
    if (str_starts_with($contents, "\xEF\xBB\xBF")) {
        $contents = substr($contents, 3);
    }

    $rows = [];
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $contents);
    rewind($handle);
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        if ($row === [null]) {
            continue;
        }
        $rows[] = $row;
    }
    fclose($handle);

    return $rows;
}

it('CSV: honors a column state_resolver for computed + formatted cells (#206)', function (): void {
    $columns = [
        [
            'name' => 'full_title',
            'label' => 'Full Title',
            'type' => 'computed',
            'state_resolver' => fn (array $record): string => 'COMPUTED:'.$record['title'],
        ],
        [
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
            'state_resolver' => fn (array $record): string => strtoupper((string) $record['title']),
        ],
    ];

    $rows = [
        ['title' => 'alpha'],
        ['title' => 'beta'],
    ];

    (new CsvExporter)->export($rows, $columns, $this->tempFile);

    expect(readStateCsvRows($this->tempFile))->toBe([
        ['Full Title', 'Title'],
        ['COMPUTED:alpha', 'ALPHA'],
        ['COMPUTED:beta', 'BETA'],
    ]);
});

it('CSV: a column without a state_resolver still reads the raw attribute (no regression, #206)', function (): void {
    $columns = [
        ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
    ];

    (new CsvExporter)->export([['title' => 'raw']], $columns, $this->tempFile);

    expect(readStateCsvRows($this->tempFile))->toBe([
        ['Title'],
        ['raw'],
    ]);
});

it('XLSX: honors a column state_resolver in the written content (#206)', function (): void {
    $xlsx = tempnam(sys_get_temp_dir(), 'arqel-state-');
    @unlink($xlsx);
    $xlsx .= '.xlsx';

    $columns = [
        [
            'name' => 'full_title',
            'label' => 'Full Title',
            'type' => 'computed',
            'state_resolver' => fn (array $record): string => 'COMPUTED:'.$record['title'],
        ],
        [
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
            'state_resolver' => fn (array $record): string => strtoupper((string) $record['title']),
        ],
    ];

    (new XlsxExporter)->export([['title' => 'alpha']], $columns, $xlsx);

    $rows = [];
    foreach (SimpleExcelReader::create($xlsx)->noHeaderRow()->getRows() as $row) {
        $rows[] = array_values($row);
    }

    expect($rows)->toBe([
        ['Full Title', 'Title'],
        ['COMPUTED:alpha', 'ALPHA'],
    ]);

    @unlink($xlsx);
});

it('PDF: honors a column state_resolver in the rendered HTML (#206)', function (): void {
    $exporter = new PdfExporter;

    $reflection = new ReflectionClass($exporter);
    $method = $reflection->getMethod('renderHtml');
    $method->setAccessible(true);

    $columns = [
        [
            'name' => 'full_title',
            'label' => 'Full Title',
            'type' => 'computed',
            'state_resolver' => fn (array $record): string => 'COMPUTED:'.$record['title'],
        ],
    ];

    /** @var string $html */
    $html = $method->invoke($exporter, [['title' => 'alpha']], $columns);

    expect($html)->toContain('COMPUTED:alpha');

    // And the full export still produces a valid PDF file.
    $pdf = tempnam(sys_get_temp_dir(), 'arqel-state-');
    @unlink($pdf);
    $pdf .= '.pdf';

    $exporter->export([['title' => 'alpha']], $columns, $pdf);

    $head = (string) file_get_contents($pdf, false, null, 0, 4);
    expect($head)->toBe('%PDF');

    @unlink($pdf);
});
