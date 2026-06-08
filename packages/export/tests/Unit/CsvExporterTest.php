<?php

declare(strict_types=1);

use Arqel\Export\Exporters\CsvExporter;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    $this->tempFile = tempnam(sys_get_temp_dir(), 'arqel-csv-');
    // simple-excel infers the writer from the extension; rename to .csv.
    @unlink($this->tempFile);
    $this->tempFile .= '.csv';
});

afterEach(function (): void {
    if (isset($this->tempFile) && is_string($this->tempFile) && file_exists($this->tempFile)) {
        @unlink($this->tempFile);
    }
});

/**
 * Read a CSV file written by spatie/simple-excel back into an array of arrays.
 * Strips the UTF-8 BOM that simple-excel prepends by default.
 *
 * @return array<int, array<int, string>>
 */
function readCsvRows(string $path): array
{
    $contents = (string) file_get_contents($path);
    // Strip BOM if present.
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

it('writes header and rows to the destination path and returns it', function (): void {
    $columns = [
        ['name' => 'id', 'label' => 'ID', 'type' => 'string'],
        ['name' => 'name', 'label' => 'Name', 'type' => 'string'],
    ];

    $rows = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ];

    $result = (new CsvExporter)->export($rows, $columns, $this->tempFile);

    expect($result)->toBe($this->tempFile);
    expect(file_exists($this->tempFile))->toBeTrue();

    $parsed = readCsvRows($this->tempFile);

    expect($parsed)->toBe([
        ['ID', 'Name'],
        ['1', 'Alice'],
        ['2', 'Bob'],
    ]);
});

it('writes only the header row when given an empty iterable', function (): void {
    $columns = [
        ['name' => 'id', 'label' => 'ID', 'type' => 'string'],
        ['name' => 'name', 'label' => 'Name', 'type' => 'string'],
    ];

    (new CsvExporter)->export([], $columns, $this->tempFile);

    $parsed = readCsvRows($this->tempFile);

    expect($parsed)->toBe([
        ['ID', 'Name'],
    ]);
});

it('formats boolean cells as Yes/No', function (): void {
    $columns = [
        ['name' => 'active', 'label' => 'Active', 'type' => 'boolean'],
    ];

    (new CsvExporter)->export(
        [['active' => true], ['active' => false]],
        $columns,
        $this->tempFile,
    );

    $parsed = readCsvRows($this->tempFile);

    expect($parsed)->toBe([
        ['Active'],
        ['Yes'],
        ['No'],
    ]);
});

it('formats DateTimeInterface cells as Y-m-d for date columns', function (): void {
    $columns = [
        ['name' => 'created_at', 'label' => 'Created', 'type' => 'date'],
    ];

    (new CsvExporter)->export(
        [['created_at' => new DateTime('2026-04-29 10:00:00')]],
        $columns,
        $this->tempFile,
    );

    $parsed = readCsvRows($this->tempFile);

    expect($parsed)->toBe([
        ['Created'],
        ['2026-04-29'],
    ]);
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

    (new CsvExporter)->export([$record], $columns, $this->tempFile);

    $parsed = readCsvRows($this->tempFile);

    expect($parsed)->toBe([
        ['Author'],
        ['Carol'],
    ]);
});

it('resolves display_path against a real related model and never leaks the model JSON', function (): void {
    // Simulates the issue #152 data leak: a relationship column serialised
    // with the real `display_path` (author.name) must export the display
    // attribute, not the whole related model (which would expose every
    // attribute, e.g. secret_token).
    $author = new class extends Model
    {
        protected $guarded = [];
    };
    $author->setRawAttributes([
        'id' => 1,
        'name' => 'Jane Doe',
        'secret_token' => 'shhh-do-not-leak',
    ]);

    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->setRawAttributes(['id' => 42, 'title' => 'A Post']);
    $record->setRelation('author', $author);

    // The exact shape RelationshipColumn::make('author')->display('name')->toArray() emits.
    $columns = [
        [
            'name' => 'author',
            'label' => 'Author',
            'type' => 'relationship',
            'display_path' => 'author.name',
        ],
    ];

    (new CsvExporter)->export([$record], $columns, $this->tempFile);

    $parsed = readCsvRows($this->tempFile);
    $contents = (string) file_get_contents($this->tempFile);

    expect($parsed)->toBe([
        ['Author'],
        ['Jane Doe'],
    ])
        ->and($contents)->not->toContain('secret_token')
        ->and($contents)->not->toContain('shhh-do-not-leak');
});

it('falls back to the column name when label is missing', function (): void {
    $columns = [
        ['name' => 'sku', 'type' => 'string'],
    ];

    (new CsvExporter)->export([['sku' => 'ABC-123']], $columns, $this->tempFile);

    $parsed = readCsvRows($this->tempFile);

    expect($parsed)->toBe([
        ['sku'],
        ['ABC-123'],
    ]);
});

it('treats null values as empty strings alongside other columns', function (): void {
    $columns = [
        ['name' => 'id', 'label' => 'ID', 'type' => 'string'],
        ['name' => 'name', 'label' => 'Name', 'type' => 'string'],
    ];

    (new CsvExporter)->export([['id' => 7, 'name' => null]], $columns, $this->tempFile);

    $parsed = readCsvRows($this->tempFile);

    expect($parsed)->toBe([
        ['ID', 'Name'],
        ['7', ''],
    ]);
});

it('retains the time component for datetime-mode date columns (#217)', function (): void {
    $columns = [
        [
            'name' => 'created_at',
            'label' => 'Created',
            'type' => 'date',
            'props' => ['mode' => 'datetime', 'format' => 'Y-m-d H:i:s'],
        ],
    ];

    (new CsvExporter)->export(
        [['created_at' => new DateTime('2026-05-08 14:30:45')]],
        $columns,
        $this->tempFile,
    );

    $parsed = readCsvRows($this->tempFile);

    expect($parsed)->toBe([
        ['Created'],
        ['2026-05-08 14:30:45'],
    ]);
});

it('honours a custom date format for date-mode columns (#217)', function (): void {
    $columns = [
        [
            'name' => 'created_at',
            'label' => 'Created',
            'type' => 'date',
            'props' => ['mode' => 'date', 'format' => 'd/m/Y'],
        ],
    ];

    (new CsvExporter)->export(
        [['created_at' => new DateTime('2026-05-08 14:30:45')]],
        $columns,
        $this->tempFile,
    );

    $parsed = readCsvRows($this->tempFile);

    expect($parsed)->toBe([
        ['Created'],
        ['08/05/2026'],
    ]);
});

it('renders a relative string for since-mode date columns (#217)', function (): void {
    $columns = [
        [
            'name' => 'created_at',
            'label' => 'Created',
            'type' => 'date',
            'props' => ['mode' => 'since', 'format' => 'Y-m-d'],
        ],
    ];

    (new CsvExporter)->export(
        [['created_at' => new DateTime('-2 days')]],
        $columns,
        $this->tempFile,
    );

    $parsed = readCsvRows($this->tempFile);

    expect($parsed[0])->toBe(['Created']);
    expect($parsed[1][0])->toContain('ago');
});
