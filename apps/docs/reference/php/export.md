# `arqel-dev/export` — API Reference

Namespace `Arqel\Export\`. Export pipeline: CSV/XLSX/PDF, streaming exporters, a pre-configured bulk `Action`, and an async job for large datasets.

## `Arqel\Export\ExportFormat` (enum, string-backed)

Cases: `CSV`, `XLSX`, `PDF`.

| Method | Type | Description |
|---|---|---|
| `mimeType()` | `string` | IANA mime type for `Content-Type` headers |
| `extension()` | `string` | Enum value, no leading dot (`'csv'`, `'xlsx'`, `'pdf'`) |

## `Arqel\Export\Contracts\Exporter` (interface)

`export(iterable $rows, array<array<string, mixed>> $columns, string $destination): string` — writes the file at the absolute `$destination` path and returns the path written. Implementations are stateless.

| Implementation | Backing | Notes |
|---|---|---|
| `Exporters\CsvExporter` (final) | `spatie/simple-excel` | Streaming, UTF-8 BOM by default, CSV-formula-injection sanitized |
| `Exporters\XlsxExporter` (final) | `spatie/simple-excel` (OpenSpout) | Streaming; dates written as formatted strings, not Excel serials |
| `Exporters\PdfExporter` (final) | `dompdf/dompdf` | Renders a minimal HTML `<table>` (no Blade dependency); `setOrientation(string)` / `setPaperSize(string)` fluent setters, defaults `'portrait'`/`'a4'` |

All three implement a shared `formatCell()` convention (via `Exporters\FormatsDateCells`): `date` → formatted string honoring the column's `mode`/`format` props, `boolean` → `Yes`/`No`, `relationship` → resolved via `display_path`, fallback `(string) $value` (`null` → `''`).

Each exporter also exposes a `static streamDownload(iterable $rows, array $columns, string $filename): StreamedResponse` helper for small/sync HTTP downloads without writing to disk first — large datasets should go through the async pipeline (`ExportAction` + `ProcessExportJob`) instead.

## `Arqel\Export\Contracts\RecordsResolver` (interface)

`resolve(): iterable` — resolves the record set for a queued export. `ProcessExportJob` stores only the resolver's FQCN in the job payload (not the collection itself), so implementations should return a streaming source (lazy collection, generator, Eloquent cursor).

## `Arqel\Export\Contracts\ExportLogger` (interface)

| Method | Description |
|---|---|
| `logQueued(string $exportId, ExportFormat $format)` | |
| `logCompleted(string $exportId, string $path, ExportFormat $format)` | |
| `logFailed(string $exportId, ExportFormat $format, Throwable $exception)` | |

Default binding: `Logging\NullExportLogger` (no-op), bound via `singletonIf`. Apps override to persist an `exports` table and/or notify users.

## `Arqel\Export\Actions\ExportAction` (final, `extends Arqel\Actions\Action`)

Pre-configured bulk action that exports the current selection. Extends `Action` directly (not `BulkAction`, which is `final`) and emits `type = 'bulk'` so consumers treat it identically.

| Method | Type | Description |
|---|---|---|
| `ExportAction::make(string $name)` | `static` | Factory. Sets label `arqel::actions.export`, icon `download`, default `destinationDir` (config `arqel-export.destination_dir` or `storage_path('app/arqel-exports')`) |
| `format(ExportFormat)` | `self` | Default `ExportFormat::CSV` |
| `getFormat()` | `ExportFormat` | |
| `withColumns(array<array<string, mixed>>)` | `self` | Column descriptors handed to the exporter |
| `withDestinationDir(string)` | `self` | Override the output directory |
| `dryRun(bool = true)` | `self` | Skips the actual exporter call; `execute()` returns `path => 'dry-run'` |
| `execute(mixed $record = null, array $data = [])` | `array{path, filename, format, mimeType}` | `$record` must be an `iterable`/`Traversable`, else throws `InvalidArgumentException`. Writes `export-<uuid>.<ext>` under the destination dir |

## `Arqel\Export\Jobs\ProcessExportJob` (final, `implements ShouldQueue`)

Writes the chosen format to `<destinationDir>/export-<exportId>.<ext>`.

| Constructor param | Type | Description |
|---|---|---|
| `$exportId` | `string` | |
| `$format` | `ExportFormat` | |
| `$columns` | `array<array<string, mixed>>` | |
| `$recordsResolverClass` | `class-string<RecordsResolver>` | Resolved from the container at `handle()` time |
| `$destinationDir` | `?string` | Default `storage_path('app/arqel-exports')` when `null` |

`handle(ExportLogger $logger): void` is the entry point. On success calls `logCompleted`; on any `Throwable`, calls `logFailed` and re-throws.

## HTTP

Registered in `routes/admin.php` under `web` + `auth` (**no bundled authorization** — consumers wrap with their own middleware):

| Verb | Route | Name | Controller |
|---|---|---|---|
| GET | `/admin/exports/{exportId}/download` | `arqel.export.download` | `Http\Controllers\ExportDownloadController::download` |

`{exportId}` is constrained to `[a-f0-9-]+`. The controller globs `<dir>/export-{exportId}.*`, aborting 400 on an invalid id or 404 on zero/multiple matches; `Content-Type` is derived from `ExportFormat::tryFrom(...)?->mimeType()`.

## Example

```php
use Arqel\Export\Actions\ExportAction;
use Arqel\Export\ExportFormat;

ExportAction::make('export')
    ->format(ExportFormat::XLSX);
```

```php
use Arqel\Export\Exporters\CsvExporter;

// Sync download from a controller, small dataset:
return CsvExporter::streamDownload($rows, $columns, 'users.csv');
```

## Related

- SKILL: [`packages/export/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/export/SKILL.md)
- Source: [`packages/export/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/export/src/)
- Sibling package: [`arqel-dev/import`](/reference/php/import) (same streaming + auth-at-the-edge posture)
