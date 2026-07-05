<?php

declare(strict_types=1);

namespace Arqel\Import\Readers;

use Arqel\Import\Contracts\FileReader;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Streaming XLSX reader backed by `spatie/simple-excel` (requires ext-zip).
 */
final class XlsxReader implements FileReader
{
    public function read(string $source): iterable
    {
        foreach (SimpleExcelReader::create($source, 'xlsx')->getRows() as $row) {
            /** @var array<string, string|null> $row */
            yield $row;
        }
    }
}
