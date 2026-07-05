<?php

declare(strict_types=1);

namespace Arqel\Import\Readers;

use Arqel\Import\Contracts\FileReader;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Streaming XLSX reader backed by `spatie/simple-excel` (requires ext-zip).
 *
 * Formulas are kept as literal text (`->keepFormulas()`): a cell that
 * literally starts with `=` (e.g. a SKU/code value) is imported as-is
 * instead of being evaluated or nulled out by the underlying parser.
 */
final class XlsxReader implements FileReader
{
    public function read(string $source): iterable
    {
        foreach (SimpleExcelReader::create($source, 'xlsx')->keepFormulas()->getRows() as $row) {
            /** @var array<string, string|null> $row */
            yield $row;
        }
    }
}
