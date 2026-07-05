<?php

declare(strict_types=1);

use Arqel\Import\ImportColumn;
use Arqel\Import\Importer;
use Arqel\Import\Tests\Fixtures\Importers\StubUserImporter;
use Arqel\Import\Tests\Fixtures\Models\ImportUser;
use Illuminate\Database\Eloquent\Model;

it('derives a rules map keyed by column name', function (): void {
    $rules = (new StubUserImporter)->rules();

    expect($rules)->toBe([
        'name' => ['required', 'string'],
        'email' => ['required', 'email'],
    ]);
});

it('resolves a fresh model instance by default', function (): void {
    $record = (new StubUserImporter)->resolveRecord(['name' => 'Ada', 'email' => 'ada@example.com']);

    expect($record)->toBeInstanceOf(ImportUser::class)
        ->and($record->exists)->toBeFalse();
});

it('exposes the columns declared by the importer', function (): void {
    $columns = (new StubUserImporter)->columns();

    expect($columns)->toHaveCount(2)
        ->and($columns[0])->toBeInstanceOf(ImportColumn::class)
        ->and($columns[0]->getName())->toBe('name')
        ->and($columns[1])->toBeInstanceOf(ImportColumn::class)
        ->and($columns[1]->getName())->toBe('email');
});

it('honors a resolveRecord() override for upsert-style importers', function (): void {
    $importer = new class extends Importer
    {
        public static string $model = ImportUser::class;

        public function columns(): array
        {
            return [ImportColumn::make('name')->rules(['required', 'string'])];
        }

        public function resolveRecord(array $data): Model
        {
            $record = new ImportUser;
            $record->name = strtoupper((string) $data['name']);

            return $record;
        }
    };

    $record = $importer->resolveRecord(['name' => 'ada']);

    expect($record)->toBeInstanceOf(ImportUser::class)
        ->and($record->name)->toBe('ADA')
        ->and($record->exists)->toBeFalse();
});
