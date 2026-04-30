<?php

declare(strict_types=1);

use Arqel\Export\Contracts\ExportLogger;
use Arqel\Export\ExportFormat;
use Arqel\Export\Jobs\ProcessExportJob;
use Arqel\Export\Tests\Fixtures\FakeRecordsResolver;
use Arqel\Export\Tests\Fixtures\NotAResolver;
use Arqel\Export\Tests\Fixtures\RecordingExportLogger;
use Dompdf\Dompdf;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/arqel-export-jobs-'.bin2hex(random_bytes(4));
    mkdir($this->tempDir, 0o755, true);

    $this->columns = [
        ['name' => 'id', 'label' => 'ID'],
        ['name' => 'name', 'label' => 'Name'],
        ['name' => 'active', 'label' => 'Active', 'type' => 'boolean'],
    ];

    $this->logger = new RecordingExportLogger;
    app()->instance(ExportLogger::class, $this->logger);
});

afterEach(function (): void {
    if (is_dir($this->tempDir)) {
        foreach (glob($this->tempDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tempDir);
    }
});

it('writes the file and notifies the logger on the happy path (CSV)', function (): void {
    $exportId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    $job = new ProcessExportJob(
        exportId: $exportId,
        format: ExportFormat::CSV,
        columns: $this->columns,
        recordsResolverClass: FakeRecordsResolver::class,
        destinationDir: $this->tempDir,
    );

    $job->handle($this->logger);

    $expectedPath = $this->tempDir.'/export-'.$exportId.'.csv';
    expect(file_exists($expectedPath))->toBeTrue();

    $first = strtok((string) file_get_contents($expectedPath), "\n");
    expect($first)->toContain('Name');

    expect($this->logger->events)->toHaveCount(1);
    expect($this->logger->events[0]['event'])->toBe('completed');
    expect($this->logger->events[0]['exportId'])->toBe($exportId);
    expect($this->logger->events[0]['path'])->toBe($expectedPath);
    expect($this->logger->events[0]['format'])->toBe(ExportFormat::CSV);
});

it('uses the XLSX exporter for the XLSX format', function (): void {
    if (! extension_loaded('zip')) {
        $this->markTestSkipped('ext-zip required for XLSX exporter.');
    }

    $exportId = '11111111-2222-3333-4444-555555555555';

    $job = new ProcessExportJob(
        exportId: $exportId,
        format: ExportFormat::XLSX,
        columns: $this->columns,
        recordsResolverClass: FakeRecordsResolver::class,
        destinationDir: $this->tempDir,
    );

    $job->handle($this->logger);

    $expectedPath = $this->tempDir.'/export-'.$exportId.'.xlsx';
    expect(file_exists($expectedPath))->toBeTrue();
    expect(filesize($expectedPath))->toBeGreaterThan(0);
});

it('uses the PDF exporter for the PDF format', function (): void {
    if (! class_exists(Dompdf::class)) {
        $this->markTestSkipped('dompdf/dompdf required for PDF exporter.');
    }

    $exportId = 'ffffffff-eeee-dddd-cccc-bbbbbbbbbbbb';

    $job = new ProcessExportJob(
        exportId: $exportId,
        format: ExportFormat::PDF,
        columns: $this->columns,
        recordsResolverClass: FakeRecordsResolver::class,
        destinationDir: $this->tempDir,
    );

    $job->handle($this->logger);

    $expectedPath = $this->tempDir.'/export-'.$exportId.'.pdf';
    expect(file_exists($expectedPath))->toBeTrue();
    expect(file_get_contents($expectedPath))->toStartWith('%PDF');
});

it('creates the destination directory if it does not exist', function (): void {
    $nested = $this->tempDir.'/nested/subdir';
    expect(is_dir($nested))->toBeFalse();

    $exportId = '99999999-8888-7777-6666-555555555555';

    $job = new ProcessExportJob(
        exportId: $exportId,
        format: ExportFormat::CSV,
        columns: $this->columns,
        recordsResolverClass: FakeRecordsResolver::class,
        destinationDir: $nested,
    );

    $job->handle($this->logger);

    expect(is_dir($nested))->toBeTrue();
    expect(file_exists($nested.'/export-'.$exportId.'.csv'))->toBeTrue();

    // cleanup
    foreach (glob($nested.'/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($nested);
    @rmdir(dirname($nested));
});

it('throws InvalidArgumentException when resolver class does not implement RecordsResolver', function (): void {
    $job = new ProcessExportJob(
        exportId: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        format: ExportFormat::CSV,
        columns: $this->columns,
        recordsResolverClass: NotAResolver::class, // @phpstan-ignore argument.type
        destinationDir: $this->tempDir,
    );

    expect(fn () => $job->handle($this->logger))
        ->toThrow(InvalidArgumentException::class);

    // failure was logged
    expect($this->logger->events)->toHaveCount(1);
    expect($this->logger->events[0]['event'])->toBe('failed');
});

it('falls back to storage_path when destinationDir is null', function (): void {
    $exportId = 'dddddddd-dddd-dddd-dddd-dddddddddddd';

    // override storage_path target via app() — Orchestra storage path
    // is writable inside the testbench skeleton. We assert via reflection
    // on the job that null was preserved + the produced file lives under
    // the resolved storage_path/app/arqel-exports.
    $job = new ProcessExportJob(
        exportId: $exportId,
        format: ExportFormat::CSV,
        columns: $this->columns,
        recordsResolverClass: FakeRecordsResolver::class,
        destinationDir: null,
    );

    expect($job->destinationDir)->toBeNull();

    $job->handle($this->logger);

    $expectedDir = storage_path('app/arqel-exports');
    $expectedPath = $expectedDir.'/export-'.$exportId.'.csv';

    expect(file_exists($expectedPath))->toBeTrue();

    // cleanup
    @unlink($expectedPath);
});
