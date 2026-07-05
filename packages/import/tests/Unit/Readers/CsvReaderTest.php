<?php

declare(strict_types=1);

use Arqel\Import\Readers\CsvReader;

it('yields one associative array per data row keyed by header', function (): void {
    $rows = iterator_to_array((function () {
        yield from (new CsvReader)->read(__DIR__.'/../../Fixtures/users-valid.csv');
    })());

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['name' => 'Ada Lovelace', 'email' => 'ada@example.com'])
        ->and($rows[1]['email'])->toBe('alan@example.com');
});

it('reads lazily (returns a Generator, not a materialised array)', function (): void {
    $result = (new CsvReader)->read(__DIR__.'/../../Fixtures/users-valid.csv');

    // Generator (not merely Traversable) proves streaming: an eager array
    // cast to ArrayIterator would satisfy Traversable but not Generator.
    expect($result)->toBeInstanceOf(Generator::class);
});
