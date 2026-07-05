<?php

declare(strict_types=1);

use Arqel\Import\Contracts\ImportLogger;
use Arqel\Import\ImportFormat;
use Arqel\Import\Jobs\ProcessImportJob;
use Arqel\Import\Logging\NullImportLogger;
use Arqel\Import\Tests\Fixtures\Importers\StubUserImporter;
use Arqel\Import\Tests\Fixtures\Importers\UpsertUserImporter;
use Arqel\Import\Tests\Fixtures\Models\ImportUser;
use Illuminate\Database\QueryException;

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
    // NullImportLogger is `final` (Task 7), so the spy implements the
    // ImportLogger contract directly instead of extending it.
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

it('commits each chunk independently: an earlier chunk persists even when a later chunk fails', function (): void {
    // 104 rows: rows 1-100 are chunk 1 (all valid, unique emails). Chunk 2
    // (rows 101-104) starts with 2 valid rows, then a row that violates the
    // `email` unique constraint (DB-level failure inside the transaction),
    // then 1 more valid row that should NOT persist because the whole
    // chunk-2 transaction rolls back.
    $job = new ProcessImportJob(
        importId: 'test-import-chunks',
        format: ImportFormat::CSV,
        importerClass: StubUserImporter::class,
        sourcePath: __DIR__.'/../Fixtures/users-chunked.csv',
        failedRowsDir: sys_get_temp_dir().'/imp-'.uniqid(),
    );

    expect(fn () => $job->handle(new NullImportLogger))
        ->toThrow(QueryException::class);

    // Chunk 1 (100 rows) committed despite chunk 2 failing.
    expect(ImportUser::count())->toBe(100);
    expect(ImportUser::where('email', 'user1@example.com')->count())->toBe(1);

    // Chunk 2's valid rows before AND after the failing row did not persist.
    expect(ImportUser::where('email', 'user101@example.com')->exists())->toBeFalse();
    expect(ImportUser::where('email', 'user104@example.com')->exists())->toBeFalse();
});

it('uses a custom resolveRecord() to upsert instead of duplicating existing records', function (): void {
    ImportUser::query()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    $job = new ProcessImportJob(
        importId: 'test-import-upsert',
        format: ImportFormat::CSV,
        importerClass: UpsertUserImporter::class,
        sourcePath: __DIR__.'/../Fixtures/users-upsert.csv',
        failedRowsDir: sys_get_temp_dir().'/imp-'.uniqid(),
    );

    $job->handle(new NullImportLogger);

    // No duplicate created for the existing ada@example.com row: updated in place.
    expect(ImportUser::where('email', 'ada@example.com')->count())->toBe(1);
    expect(ImportUser::where('email', 'ada@example.com')->value('name'))->toBe('Ada Lovelace Updated');

    // The new row was inserted.
    expect(ImportUser::where('email', 'grace@example.com')->exists())->toBeTrue();

    expect(ImportUser::count())->toBe(2);
});

it('fails fast when a required-mapping header is missing from the source file, persisting nothing', function (): void {
    $dir = sys_get_temp_dir().'/imp-'.uniqid();
    $job = new ProcessImportJob(
        importId: 'test-import-missing-header',
        format: ImportFormat::CSV,
        importerClass: UpsertUserImporter::class,
        sourcePath: __DIR__.'/../Fixtures/users-missing-email-header.csv',
        failedRowsDir: $dir,
    );

    expect(fn () => $job->handle(new NullImportLogger))
        ->toThrow(InvalidArgumentException::class);

    expect(ImportUser::count())->toBe(0);
    expect(file_exists($dir.'/failed-test-import-missing-header.csv'))->toBeFalse();
});
