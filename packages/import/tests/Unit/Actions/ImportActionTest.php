<?php

declare(strict_types=1);

use Arqel\Import\Actions\ImportAction;
use Arqel\Import\ImportFormat;
use Arqel\Import\Tests\Fixtures\Importers\StubUserImporter;

it('builds fluently with importer and format defaults to CSV', function (): void {
    $action = ImportAction::make('import');

    expect($action->getFormat())->toBe(ImportFormat::CSV)
        ->and($action->getImporterClass())->toBeNull();
});

it('sets importer class and format fluently', function (): void {
    $action = ImportAction::make('import')
        ->importer(StubUserImporter::class)
        ->format(ImportFormat::XLSX);

    expect($action->getImporterClass())->toBe(StubUserImporter::class)
        ->and($action->getFormat())->toBe(ImportFormat::XLSX);
});

it('inherits the per-action authorization gate from the base Action', function (): void {
    $action = ImportAction::make('import')
        ->authorize(fn (?Illuminate\Contracts\Auth\Authenticatable $user): bool => false);

    expect($action->canBeExecutedBy(null))->toBeFalse();

    $allowed = ImportAction::make('import')
        ->authorize(fn (?Illuminate\Contracts\Auth\Authenticatable $user): bool => true);

    expect($allowed->canBeExecutedBy(null))->toBeTrue();
});

it('is authorized by default when no authorize predicate is declared', function (): void {
    $action = ImportAction::make('import');

    expect($action->canBeExecutedBy(null))->toBeTrue();
});
