# `arqel-dev/import` — API Reference

Namespace `Arqel\Import\`. CSV/XLSX import pipeline: declarative columns, chunked+transactional processing, failed-rows CSV download.

## `Arqel\Import\Importer` (abstract)

Base class a consumer app extends to declare an import.

| Method | Type | Description |
|---|---|---|
| `$model` | `static class-string<Model>` | Target Eloquent model |
| `columns()` | `array<ImportColumn>` (abstract) | Column descriptors — declare it |
| `resolveRecord(array $data)` | `Model` | Resolves the model a validated row maps to. Default: `new $model` (insert). Override for upsert, e.g. `User::firstOrNew(['email' => $data['email']])` |
| `rules()` | `array<string, array>` | Validation rules keyed by column name, derived from `columns()` |

## `Arqel\Import\ImportColumn` (final)

Declarative column descriptor. Factory: `ImportColumn::make($name)` — `$name` matches the file's header.

| Method | Type | Description |
|---|---|---|
| `label(string)` | `self` | Display label (default = name) |
| `rules(array)` | `self` | Per-row Laravel validation rules |
| `fillUsing(Closure)` | `self` | Transforms the raw cell value before validation |
| `requiredMapping(bool = true)` | `self` | Marks the header as mandatory — a missing header aborts the job with a setup error instead of silently nulling every row |
| `getName()` / `getLabel()` / `getRules()` / `isMappingRequired()` | getters | |
| `applyFill(?string $raw)` | `mixed` | Runs the configured `fillUsing` (or returns `$raw` unchanged) |

## `Arqel\Import\ImportFormat` (enum, string-backed)

Cases: `CSV`, `XLSX`.

| Method | Type | Description |
|---|---|---|
| `extension()` | `string` | Enum value (`'csv'`, `'xlsx'`) |
| `fromExtension(string)` | `self` (static) | Throws `InvalidArgumentException` for unsupported extensions |

## `Arqel\Import\Contracts\FileReader` (interface)

`read(string $source): iterable<int, array<string, string|null>>` — streams rows lazily, keyed by header, never loading the whole file into memory. Implementations: `Readers\CsvReader`, `Readers\XlsxReader` (both backed by `spatie/simple-excel`; XLSX additionally requires `ext-zip`).

## `Arqel\Import\Contracts\ImportLogger` (interface)

Lifecycle/progress hook.

| Method | Description |
|---|---|
| `logQueued(string $importId, ImportFormat $format)` | Job dispatched |
| `progress(string $importId, int $imported, int $skipped)` | Called after each chunk |
| `logCompleted(string $importId, int $imported, int $skipped, ?string $failedRowsPath)` | Job finished |
| `logFailed(string $importId, ImportFormat $format, Throwable $exception)` | Job threw |

Default binding: `Arqel\Import\Logging\NullImportLogger` (no-op), bound via `singletonIf`. Apps override to persist an `imports` table and/or notify users.

## `Arqel\Import\Jobs\ProcessImportJob` (final, `implements ShouldQueue`)

Streams the source file in chunks of 100 rows, each inside its own `DB::transaction()`. Validates every row via `Validator::make($data, $rules)`; failed rows are collected (with a synthetic `_errors` column) rather than aborting the job, and written to a downloadable CSV at the end.

| Constructor param | Type | Description |
|---|---|---|
| `$importId` | `string` | Correlates progress/logging calls |
| `$format` | `ImportFormat` | |
| `$importerClass` | `class-string<Importer>` | |
| `$sourcePath` | `string` | Absolute path of the uploaded file |
| `$failedRowsDir` | `?string` | Default `storage_path('app/arqel-imports')` when `null` |

`handle(ImportLogger $logger): void` is the entry point (Laravel resolves `$logger` from the container). Failed-row CSV cells are sanitized against CSV formula injection (a leading `= + - @` or control char is apostrophe-prefixed).

## `Arqel\Import\Actions\ImportAction` (final, `extends Arqel\Actions\Action`)

Toolbar action that opens the import upload flow for a Resource. Extending the framework `Action` means it inherits per-action authorization at every entry point.

| Method | Type | Description |
|---|---|---|
| `ImportAction::make(string $name)` | `static` | Factory. Sets label `arqel-import::import.action` + icon `upload` |
| `importer(class-string<Importer>)` | `self` | |
| `format(ImportFormat)` | `self` | Default `ImportFormat::CSV` |
| `getImporterClass()` / `getFormat()` | getters | |

## HTTP

Registered in `routes/admin.php` under `web` + `auth` (no bundled authorization beyond that — apps wrap with their own gate):

| Verb | Route | Name | Controller |
|---|---|---|---|
| POST | `admin/imports` | `arqel.imports.upload` | `Http\Controllers\ImportUploadController` |
| GET | `admin/imports/{importId}/failed-rows` | `arqel.imports.failed-rows` | `Http\Controllers\FailedRowsDownloadController` |

## Example

```php
use Arqel\Import\Importer;
use Arqel\Import\ImportColumn;
use App\Models\User;

final class UserImporter extends Importer
{
    public static string $model = User::class;

    public function columns(): array
    {
        return [
            ImportColumn::make('name')->rules(['required', 'string', 'max:255']),
            ImportColumn::make('email')
                ->rules(['required', 'email', 'unique:users,email'])
                ->requiredMapping(),
            ImportColumn::make('role')
                ->fillUsing(fn (?string $raw) => strtolower($raw ?? 'member')),
        ];
    }

    public function resolveRecord(array $data): User
    {
        return User::firstOrNew(['email' => $data['email']]);
    }
}
```

```php
use Arqel\Import\Actions\ImportAction;
use Arqel\Import\ImportFormat;

ImportAction::make('import')
    ->importer(UserImporter::class)
    ->format(ImportFormat::CSV);
```

## Related

- SKILL: [`packages/import/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/import/SKILL.md)
- Source: [`packages/import/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/import/src/)
- Sibling package: [`arqel-dev/export`](/reference/php/export) (same streaming + auth-at-the-edge posture)
