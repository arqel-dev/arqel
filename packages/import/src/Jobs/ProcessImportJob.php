<?php

declare(strict_types=1);

namespace Arqel\Import\Jobs;

use Arqel\Import\Contracts\FileReader;
use Arqel\Import\Contracts\ImportLogger;
use Arqel\Import\Importer;
use Arqel\Import\ImportFormat;
use Arqel\Import\Readers\CsvReader;
use Arqel\Import\Readers\XlsxReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Throwable;

/**
 * Streams an uploaded import file in chunks, validates each row against
 * the importer's columns, persists valid rows (per-chunk transaction),
 * and collects invalid rows into a downloadable failed-rows CSV.
 */
final class ProcessImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const CHUNK_SIZE = 100;

    /** @param class-string<Importer> $importerClass */
    public function __construct(
        public readonly string $importId,
        public readonly ImportFormat $format,
        public readonly string $importerClass,
        public readonly string $sourcePath,
        public readonly ?string $failedRowsDir = null,
    ) {}

    public function handle(ImportLogger $logger): void
    {
        try {
            $importer = $this->makeImporter();
            $columns = $importer->columns();
            $rules = $importer->rules();
            $reader = $this->makeReader();

            $imported = 0;
            $skipped = 0;
            /** @var list<array<string, mixed>> $failedRows */
            $failedRows = [];

            foreach ($this->chunk($reader->read($this->sourcePath), self::CHUNK_SIZE) as $chunk) {
                DB::transaction(function () use ($chunk, $columns, $rules, $importer, &$imported, &$skipped, &$failedRows): void {
                    foreach ($chunk as $raw) {
                        $data = [];
                        foreach ($columns as $column) {
                            $data[$column->getName()] = $column->applyFill($raw[$column->getName()] ?? null);
                        }

                        $validator = Validator::make($data, $rules);
                        if ($validator->fails()) {
                            $failedRows[] = $raw + ['_errors' => implode('; ', $validator->errors()->all())];
                            $skipped++;

                            continue;
                        }

                        $importer->resolveRecord($data)->fill($data)->save();
                        $imported++;
                    }
                });

                $logger->progress($this->importId, $imported, $skipped);
            }

            $failedPath = $failedRows === [] ? null : $this->writeFailedRows($failedRows);
            $logger->logCompleted($this->importId, $imported, $skipped, $failedPath);
        } catch (Throwable $exception) {
            $logger->logFailed($this->importId, $this->format, $exception);

            throw $exception;
        }
    }

    private function makeImporter(): Importer
    {
        /** @var mixed $importer */
        $importer = app($this->importerClass);
        if (! $importer instanceof Importer) {
            throw new InvalidArgumentException(sprintf('Importer [%s] must extend %s.', $this->importerClass, Importer::class));
        }

        return $importer;
    }

    private function makeReader(): FileReader
    {
        return match ($this->format) {
            ImportFormat::CSV => new CsvReader,
            ImportFormat::XLSX => new XlsxReader,
        };
    }

    /**
     * @param iterable<int, array<string, mixed>> $rows
     *
     * @return iterable<int, list<array<string, mixed>>>
     */
    private function chunk(iterable $rows, int $size): iterable
    {
        $buffer = [];
        foreach ($rows as $row) {
            $buffer[] = $row;
            if (count($buffer) >= $size) {
                yield $buffer;
                $buffer = [];
            }
        }
        if ($buffer !== []) {
            yield $buffer;
        }
    }

    /** @param list<array<string, mixed>> $failedRows */
    private function writeFailedRows(array $failedRows): string
    {
        $dir = rtrim($this->failedRowsDir ?? storage_path('app/arqel-imports'), '/');
        if (! is_dir($dir) && ! @mkdir($dir, 0o755, true) && ! is_dir($dir)) {
            throw new InvalidArgumentException(sprintf('Unable to create failed-rows directory [%s].', $dir));
        }

        $path = $dir.'/failed-'.$this->importId.'.csv';
        $writer = SimpleExcelWriter::create($path);
        foreach ($failedRows as $row) {
            $writer->addRow(array_map(
                fn ($v): string => $this->sanitizeForCsv((string) ($v ?? '')),
                $row,
            ));
        }
        $writer->close();

        return $path;
    }

    /**
     * Neutralize CSV formula injection: a cell whose first character is one of
     * `= + - @` or a control char (tab, CR, LF) is interpreted as a formula by
     * Excel/Sheets. Prefixing an apostrophe forces the spreadsheet to treat the
     * value as literal text. Erring toward over-escaping (e.g. a bare "-5")
     * is deliberate — leaking a live formula is the worse failure. See OWASP
     * "CSV Injection".
     */
    private function sanitizeForCsv(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        return match ($value[0]) {
            '=', '+', '-', '@', "\t", "\r", "\n" => "'".$value,
            default => $value,
        };
    }
}
