<?php

declare(strict_types=1);

use Arqel\Import\Readers\CsvReader;
use OpenSpout\Common\Exception\IOException;

it('yields one associative array per data row keyed by header', function (): void {
    $rows = iterator_to_array((function () {
        yield from (new CsvReader)->read(__DIR__.'/../../Fixtures/users-valid.csv');
    })());

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['name' => 'Ada Lovelace', 'email' => 'ada@example.com'])
        ->and($rows[1]['email'])->toBe('alan@example.com');
});

it('reads lazily (the file is only opened once the generator is iterated)', function (): void {
    // A plain `toBeInstanceOf(Traversable::class)` proves nothing about
    // laziness — an eagerly materialised ArrayIterator satisfies it too.
    // A deterministic, behaviour-based proof: point read() at a file path
    // that does not exist. If reading were eager, opening/parsing the
    // source would happen inside read() itself and throw immediately.
    // Because read() is a generator function, its body — including the
    // call into SimpleExcelReader — provably does not run until the
    // first iteration step, so constructing the result must not throw.
    $missingPath = __DIR__.'/../../Fixtures/does-not-exist-'.uniqid().'.csv';

    $result = (new CsvReader)->read($missingPath);

    expect($result)->toBeInstanceOf(Generator::class);

    // Only the first advance actually touches the filesystem, and that's
    // where the failure surfaces — proving nothing ran beforehand.
    expect(fn () => $result->rewind())->toThrow(IOException::class);
});

it('preserves a literal =-prefixed cell instead of nulling it as a formula', function (): void {
    $rows = iterator_to_array((function () {
        yield from (new CsvReader)->read(__DIR__.'/../../Fixtures/users-formula-code.csv');
    })());

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['code'])->toBe('=SKU001');
});
