<?php

declare(strict_types=1);

use Arqel\Import\Readers\XlsxReader;
use Spatie\SimpleExcel\SimpleExcelWriter;

beforeEach(function (): void {
    if (! extension_loaded('zip')) {
        $this->markTestSkipped('ext-zip not available (host baseline); XLSX verified on CI.');
    }
});

it('yields one associative array per data row keyed by header', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    SimpleExcelWriter::create($path)
        ->addRow(['name' => 'Ada Lovelace', 'email' => 'ada@example.com'])
        ->addRow(['name' => 'Alan Turing', 'email' => 'alan@example.com'])
        ->close();

    $rows = iterator_to_array((function () use ($path) {
        yield from (new XlsxReader)->read($path);
    })());

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    @unlink($path);
});
