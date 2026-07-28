<?php

declare(strict_types=1);

use Arqel\Export\Models\Export;
use Illuminate\Support\Facades\Schema;

it('creates the arqel_exports table with the expected columns', function (): void {
    expect(Schema::hasTable('arqel_exports'))->toBeTrue();
    expect(Schema::hasColumns('arqel_exports', [
        'id', 'owner_user_id', 'format', 'path', 'expires_at', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('uses a string uuid primary key and casts expires_at', function (): void {
    $export = new Export;

    expect($export->getKeyName())->toBe('id');
    expect($export->getKeyType())->toBe('string');
    expect($export->getIncrementing())->toBeFalse();

    $created = Export::create([
        'id' => '11111111-1111-4111-8111-111111111111',
        'owner_user_id' => '42',
        'format' => 'csv',
        'path' => '/tmp/export-x.csv',
        'expires_at' => null,
    ]);

    expect($created->exists)->toBeTrue();
    expect($created->getCasts()['expires_at'] ?? null)->toBe('datetime');
});
