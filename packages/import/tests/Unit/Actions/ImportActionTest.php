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
