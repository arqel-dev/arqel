<?php

declare(strict_types=1);

use Arqel\Import\Contracts\ImportLogger;
use Arqel\Import\ImportColumn;
use Arqel\Import\Importer;
use Arqel\Import\ImportFormat;
use Arqel\Import\Jobs\ProcessImportJob;
use Arqel\Import\Logging\NullImportLogger;
use Arqel\Import\Tests\Fixtures\Importers\StubUserImporter;
use Arqel\Import\Tests\Fixtures\Models\ImportUser;

/**
 * Importer whose `email` column is a required mapping — used to exercise the
 * ProcessImportJob header guard.
 */
final class RequiredHeaderImporter extends Importer
{
    public static string $model = ImportUser::class;

    public function columns(): array
    {
        return [
            ImportColumn::make('name')->rules(['required', 'string']),
            ImportColumn::make('email')->rules(['required', 'email'])->requiredMapping(),
        ];
    }
}

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

it('neutralizes formula-injection payloads in the failed-rows CSV', function (): void {
    $dir = sys_get_temp_dir().'/imp-'.uniqid();
    $job = new ProcessImportJob(
        importId: 'test-import-formula',
        format: ImportFormat::CSV,
        importerClass: StubUserImporter::class,
        sourcePath: __DIR__.'/../Fixtures/users-formula.csv',
        failedRowsDir: $dir,
    );

    $job->handle(new NullImportLogger);

    $failed = $dir.'/failed-test-import-formula.csv';
    expect(file_exists($failed))->toBeTrue();
    $contents = file_get_contents($failed);

    // The dangerous cell must be neutralized with a leading apostrophe,
    // never written as a bare formula that Excel/Sheets would execute.
    expect($contents)->toContain('\'+cmd|\' /C calc\'!A0')
        ->and($contents)->not->toContain(',+cmd|');
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

it('imports every data row when a required header is present (guard does not eat the first row)', function (): void {
    // Regression guard: the header check peeks the first row of the reader's
    // generator; the chunk loop must then still see ALL data rows, including
    // the first — a naive peek that advances the generator would silently drop
    // row 1. users-valid.csv has exactly 2 data rows (Ada, Alan).
    (new ProcessImportJob(
        importId: 'test-import-req-ok',
        format: ImportFormat::CSV,
        importerClass: RequiredHeaderImporter::class,
        sourcePath: __DIR__.'/../Fixtures/users-valid.csv',
        failedRowsDir: sys_get_temp_dir().'/imp-'.uniqid(),
    ))->handle(new NullImportLogger);

    expect(ImportUser::count())->toBe(2)
        ->and(ImportUser::where('email', 'ada@example.com')->exists())->toBeTrue()
        ->and(ImportUser::where('email', 'alan@example.com')->exists())->toBeTrue();
});

it('fails fast and persists nothing when a required header is missing', function (): void {
    $job = new ProcessImportJob(
        importId: 'test-import-req-missing',
        format: ImportFormat::CSV,
        importerClass: RequiredHeaderImporter::class,
        sourcePath: __DIR__.'/../Fixtures/users-missing-header.csv',
        failedRowsDir: sys_get_temp_dir().'/imp-'.uniqid(),
    );

    expect(fn () => $job->handle(new NullImportLogger))
        ->toThrow(InvalidArgumentException::class);
    expect(ImportUser::count())->toBe(0);
});
