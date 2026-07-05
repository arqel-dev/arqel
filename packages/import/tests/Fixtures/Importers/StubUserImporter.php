<?php

declare(strict_types=1);

namespace Arqel\Import\Tests\Fixtures\Importers;

use Arqel\Import\ImportColumn;
use Arqel\Import\Importer;
use Arqel\Import\Tests\Fixtures\Models\ImportUser;

final class StubUserImporter extends Importer
{
    public static string $model = ImportUser::class;

    public function columns(): array
    {
        return [
            ImportColumn::make('name')->rules(['required', 'string']),
            ImportColumn::make('email')->rules(['required', 'email']),
        ];
    }
}
