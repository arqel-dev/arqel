<?php

declare(strict_types=1);

namespace Arqel\Import\Contracts;

/**
 * Format-agnostic file reader contract.
 *
 * Implementations stream rows lazily from a file, yielding one
 * associative array per data row keyed by header. They never load
 * the whole file into memory.
 *
 *   - `CsvReader`  → Task 3 (spatie/simple-excel)
 *   - `XlsxReader` → Task 4 (spatie/simple-excel)
 */
interface FileReader
{
    /**
     * @param string $source Absolute path of the file to read.
     *
     * @return iterable<int, array<string, string|null>> Rows keyed by header.
     */
    public function read(string $source): iterable;
}
