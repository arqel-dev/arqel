<?php

declare(strict_types=1);

use Arqel\Export\Actions\ExportAction;
use Arqel\Export\ExportFormat;
use Dompdf\Dompdf;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/arqel-export-action-'.uniqid();
    @mkdir($this->tempDir, 0o755, true);
    $this->writtenFiles = [];
});

afterEach(function (): void {
    if (isset($this->tempDir) && is_string($this->tempDir) && is_dir($this->tempDir)) {
        foreach ((array) glob($this->tempDir.'/*') as $file) {
            if (is_string($file) && file_exists($file)) {
                @unlink($file);
            }
        }
        @rmdir($this->tempDir);
    }
});

it('writes a CSV file at the configured destination dir', function (): void {
    $columns = [
        ['name' => 'id', 'label' => 'ID', 'type' => 'string'],
        ['name' => 'name', 'label' => 'Name', 'type' => 'string'],
    ];

    $records = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ];

    $payload = ExportAction::make('export')
        ->format(ExportFormat::CSV)
        ->withColumns($columns)
        ->withDestinationDir($this->tempDir)
        ->execute($records);

    expect($payload['format'])->toBe('csv');
    expect($payload['mimeType'])->toBe('text/csv');
    expect($payload['filename'])->toEndWith('.csv');
    expect(file_exists($payload['path']))->toBeTrue();

    $contents = (string) file_get_contents($payload['path']);
    if (str_starts_with($contents, "\xEF\xBB\xBF")) {
        $contents = substr($contents, 3);
    }
    $firstLine = strtok($contents, "\n");

    expect($firstLine)->toContain('ID');
    expect($firstLine)->toContain('Name');
});

it('writes an XLSX file at the configured destination dir', function (): void {
    if (! extension_loaded('zip')) {
        $this->markTestSkipped('ext-zip is required for XLSX export');
    }

    $columns = [['name' => 'id', 'label' => 'ID', 'type' => 'string']];

    $payload = ExportAction::make('export')
        ->format(ExportFormat::XLSX)
        ->withColumns($columns)
        ->withDestinationDir($this->tempDir)
        ->execute([['id' => 1]]);

    expect($payload['format'])->toBe('xlsx');
    expect($payload['filename'])->toEndWith('.xlsx');
    expect(file_exists($payload['path']))->toBeTrue();
});

it('writes a PDF file at the configured destination dir', function (): void {
    if (! class_exists(Dompdf::class)) {
        $this->markTestSkipped('dompdf/dompdf is required for PDF export');
    }

    $columns = [['name' => 'id', 'label' => 'ID', 'type' => 'string']];

    $payload = ExportAction::make('export')
        ->format(ExportFormat::PDF)
        ->withColumns($columns)
        ->withDestinationDir($this->tempDir)
        ->execute([['id' => 1]]);

    expect($payload['format'])->toBe('pdf');
    expect($payload['mimeType'])->toBe('application/pdf');
    expect($payload['filename'])->toEndWith('.pdf');
    expect(file_exists($payload['path']))->toBeTrue();
    expect(substr((string) file_get_contents($payload['path']), 0, 4))->toBe('%PDF');
});

it('returns dry-run payload without writing to the filesystem', function (): void {
    $payload = ExportAction::make('export')
        ->format(ExportFormat::CSV)
        ->withColumns([['name' => 'id', 'label' => 'ID']])
        ->withDestinationDir($this->tempDir)
        ->dryRun()
        ->execute([['id' => 1]]);

    expect($payload['path'])->toBe('dry-run');
    expect($payload['format'])->toBe('csv');
    expect($payload['filename'])->toEndWith('.csv');
    expect(glob($this->tempDir.'/*'))->toBe([]);
});

it('throws InvalidArgumentException when record is null', function (): void {
    ExportAction::make('export')
        ->format(ExportFormat::CSV)
        ->withDestinationDir($this->tempDir)
        ->execute(null);
})->throws(InvalidArgumentException::class, 'expects an iterable');

it('throws InvalidArgumentException when record is a scalar string', function (): void {
    ExportAction::make('export')
        ->format(ExportFormat::CSV)
        ->withDestinationDir($this->tempDir)
        ->execute('not iterable');
})->throws(InvalidArgumentException::class, 'expects an iterable');

it('embeds the format extension in the filename', function (): void {
    $payload = ExportAction::make('export')
        ->format(ExportFormat::CSV)
        ->withColumns([['name' => 'id', 'label' => 'ID']])
        ->withDestinationDir($this->tempDir)
        ->dryRun()
        ->execute([]);

    expect($payload['filename'])->toMatch('/^export-\d{8}-\d{6}\.csv$/');
});

it('produces a CSV with empty columns without throwing', function (): void {
    $payload = ExportAction::make('export')
        ->format(ExportFormat::CSV)
        ->withColumns([])
        ->withDestinationDir($this->tempDir)
        ->execute([['id' => 1]]);

    expect(file_exists($payload['path']))->toBeTrue();
});

it('accepts a Laravel Collection as the records iterable', function (): void {
    $payload = ExportAction::make('export')
        ->format(ExportFormat::CSV)
        ->withColumns([['name' => 'id', 'label' => 'ID']])
        ->withDestinationDir($this->tempDir)
        ->execute(collect([['id' => 1], ['id' => 2]]));

    expect(file_exists($payload['path']))->toBeTrue();
});
