<?php

declare(strict_types=1);

use Arqel\Import\Tests\Fixtures\Importers\StubUserImporter;
use Arqel\Import\Tests\Fixtures\Models\ImportUser;

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
