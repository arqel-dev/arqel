<?php

declare(strict_types=1);

namespace Arqel\Import\Readers;

use Arqel\Import\Contracts\FileReader;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Streaming CSV reader backed by `spatie/simple-excel`.
 *
 * Yields one associative array per data row, keyed by the file's
 * header row. Reading is lazy — the whole file is never held in memory.
 * Formulas are kept as literal text (`->keepFormulas()`): a cell that
 * literally starts with `=` (e.g. a SKU/code value) is imported as-is
 * instead of being evaluated or nulled out by the underlying parser.
 */
final class CsvReader implements FileReader
{
    public function read(string $source): iterable
    {
        foreach (SimpleExcelReader::create($source, 'csv')->keepFormulas()->getRows() as $row) {
            /** @var array<string, string|null> $row */
            yield $row;
        }
    }
}
