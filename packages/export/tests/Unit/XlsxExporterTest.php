<?php

declare(strict_types=1);

use Arqel\Export\Exporters\XlsxExporter;
use Spatie\SimpleExcel\SimpleExcelReader;

beforeEach(function (): void {
    if (! extension_loaded('zip')) {
        $this->markTestSkipped('ext-zip required for XLSX (OpenSpout). Install php-zip to enable XlsxExporter test coverage.');
    }

    $this->tempFile = tempnam(sys_get_temp_dir(), 'arqel-xlsx-');
    @unlink($this->tempFile);
    $this->tempFile .= '.xlsx';
});

afterEach(function (): void {
    if (isset($this->tempFile) && is_string($this->tempFile) && file_exists($this->tempFile)) {
        @unlink($this->tempFile);
    }
});

/**
 * Round-trip read every row as numeric arrays (header included).
 *
 * @return array<int, array<int, mixed>>
 */
function readXlsxRows(string $path): array
{
    $rows = [];
    foreach (SimpleExcelReader::create($path)->noHeaderRow()->getRows() as $row) {
        $rows[] = array_values($row);
    }

    return $rows;
}

/**
 * Return the raw `xl/worksheets/sheet1.xml` markup of an XLSX file.
 *
 * Reading the worksheet XML directly bypasses the OpenSpout reader, which
 * reconstructs a value from the stored cell and so masks how the cell is
 * actually rendered by Excel/LibreOffice (issue #106): a date written as a
 * bare Excel serial under the General number format shows up as the literal
 * number, never a date.
 */
function rawXlsxSheetXml(string $path): string
{
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    return $xml;
}

it('writes header and rows to the destination path and returns it', function (): void {
    $columns = [
        ['name' => 'id', 'label' => 'ID', 'type' => 'string'],
        ['name' => 'name', 'label' => 'Name', 'type' => 'string'],
    ];

    $rows = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ];

    $result = (new XlsxExporter)->export($rows, $columns, $this->tempFile);

    expect($result)->toBe($this->tempFile);
    expect(file_exists($this->tempFile))->toBeTrue();

    $parsed = readXlsxRows($this->tempFile);

    expect($parsed[0])->toBe(['ID', 'Name']);
    expect($parsed[1][0])->toEqual(1);
    expect($parsed[1][1])->toBe('Alice');
    expect($parsed[2][0])->toEqual(2);
    expect($parsed[2][1])->toBe('Bob');
});

it('writes only the header row when given an empty iterable', function (): void {
    $columns = [
        ['name' => 'id', 'label' => 'ID', 'type' => 'string'],
        ['name' => 'name', 'label' => 'Name', 'type' => 'string'],
    ];

    (new XlsxExporter)->export([], $columns, $this->tempFile);

    $parsed = readXlsxRows($this->tempFile);

    expect($parsed)->toBe([
        ['ID', 'Name'],
    ]);
});

it('formats boolean cells as Yes/No', function (): void {
    $columns = [
        ['name' => 'active', 'label' => 'Active', 'type' => 'boolean'],
    ];

    (new XlsxExporter)->export(
        [['active' => true], ['active' => false]],
        $columns,
        $this->tempFile,
    );

    $parsed = readXlsxRows($this->tempFile);

    expect($parsed)->toBe([
        ['Active'],
        ['Yes'],
        ['No'],
    ]);
});

it('renders date cells as readable Y-m-d text, not a bare Excel serial', function (): void {
    // Regression for #106: the previous implementation passed the
    // DateTimeInterface straight through, so OpenSpout wrote the Excel serial
    // (e.g. 46141.4375) under the General number format with no date numFmt
    // attached — Excel/LibreOffice then displayed the literal number instead
    // of a date. We now format dates to a string like CsvExporter/PdfExporter,
    // guaranteeing a readable cell everywhere. The old assertion (round-trip
    // via the reader yields a DateTime) encoded the bug: the reader is the
    // only place the serial ever looked like a date.
    $columns = [
        ['name' => 'created_at', 'label' => 'Created', 'type' => 'date'],
    ];

    (new XlsxExporter)->export(
        [['created_at' => new DateTimeImmutable('2026-04-29 10:00:00')]],
        $columns,
        $this->tempFile,
    );

    $xml = rawXlsxSheetXml($this->tempFile);

    // The cell holds the formatted date as inline text, not a numeric serial.
    expect($xml)->toContain('2026-04-29');
    expect($xml)->not->toContain('46141'); // Excel serial for 2026-04-29.

    // And the reader sees the same string, not a reconstructed DateTime.
    $parsed = readXlsxRows($this->tempFile);
    expect($parsed[0])->toBe(['Created']);
    expect($parsed[1][0])->toBe('2026-04-29');
});

it('uses display_path for relationship columns', function (): void {
    $columns = [
        [
            'name' => 'author',
            'label' => 'Author',
            'type' => 'relationship',
            'display_path' => 'author.name',
        ],
    ];

    $record = ['author' => ['name' => 'Carol']];

    (new XlsxExporter)->export([$record], $columns, $this->tempFile);

    $parsed = readXlsxRows($this->tempFile);

    expect($parsed)->toBe([
        ['Author'],
        ['Carol'],
    ]);
});

it('falls back to the column name when label is missing', function (): void {
    $columns = [
        ['name' => 'sku', 'type' => 'string'],
    ];

    (new XlsxExporter)->export([['sku' => 'ABC-123']], $columns, $this->tempFile);

    $parsed = readXlsxRows($this->tempFile);

    expect($parsed)->toBe([
        ['sku'],
        ['ABC-123'],
    ]);
});
