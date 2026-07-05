<?php

declare(strict_types=1);

use Arqel\Import\Contracts\ImportLogger;
use Arqel\Import\ImportFormat;
use Arqel\Import\Jobs\ProcessImportJob;
use Arqel\Import\Logging\NullImportLogger;
use Arqel\Import\Tests\Fixtures\Importers\StubUserImporter;
use Arqel\Import\Tests\Fixtures\Models\ImportUser;

it('imports valid rows and skips invalid ones into a failed-rows CSV', function (): void {
    $dir = sys_get_temp_dir().'/imp-'.uniqid();
    $job = new ProcessImportJob(
        importId: 'test-import-1',
        format: ImportFormat::CSV,
        importerClass: StubUserImporter::class,
        sourcePath: __DIR__.'/../Fixtures/users-mixed.csv',
        failedRowsDir: $dir,
    );

    $job->handle(new NullImportLogger);

    // 2 valid rows persisted, 1 invalid skipped
    expect(ImportUser::count())->toBe(2);
    expect(ImportUser::where('email', 'ada@example.com')->exists())->toBeTrue();
    expect(ImportUser::where('email', 'not-an-email')->exists())->toBeFalse();

    // failed-rows CSV written with the bad row + an _errors column
    $failed = $dir.'/failed-test-import-1.csv';
    expect(file_exists($failed))->toBeTrue();
    $contents = file_get_contents($failed);
    expect($contents)->toContain('not-an-email')
        ->and($contents)->toContain('_errors');
});

it('reports progress and completion counts to the logger', function (): void {
    $spy = new class implements ImportLogger
    {
        public array $progressCalls = [];

        public array $completed = [];

        public function logQueued(string $importId, ImportFormat $format): void
        {
            // no-op
        }

        public function progress(string $importId, int $imported, int $skipped): void
        {
            $this->progressCalls[] = [$imported, $skipped];
        }

        public function logCompleted(string $importId, int $imported, int $skipped, ?string $failedRowsPath): void
        {
            $this->completed = [$imported, $skipped, $failedRowsPath];
        }

        public function logFailed(string $importId, ImportFormat $format, Throwable $exception): void
        {
            // no-op
        }
    };

    (new ProcessImportJob(
        importId: 'test-import-2',
        format: ImportFormat::CSV,
        importerClass: StubUserImporter::class,
        sourcePath: __DIR__.'/../Fixtures/users-mixed.csv',
        failedRowsDir: sys_get_temp_dir().'/imp-'.uniqid(),
    ))->handle($spy);

    expect($spy->completed[0])->toBe(2)   // imported
        ->and($spy->completed[1])->toBe(1) // skipped
        ->and($spy->completed[2])->not->toBeNull();
    expect($spy->progressCalls)->not->toBeEmpty();
});
