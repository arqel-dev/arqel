<?php

declare(strict_types=1);

namespace Arqel\Import\Tests\Fixtures\Importers;

use Arqel\Import\ImportColumn;
use Arqel\Import\Importer;
use Arqel\Import\Tests\Fixtures\Models\ImportUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Importer that upserts on `email` instead of always inserting, to
 * exercise {@see Importer::resolveRecord()} overrides.
 */
final class UpsertUserImporter extends Importer
{
    public static string $model = ImportUser::class;

    public function columns(): array
    {
        return [
            ImportColumn::make('name')->rules(['required', 'string']),
            ImportColumn::make('email')->rules(['required', 'email'])->requiredMapping(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function resolveRecord(array $data): Model
    {
        return ImportUser::firstOrNew(['email' => $data['email']]);
    }
}
