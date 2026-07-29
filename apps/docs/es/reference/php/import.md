# `arqel-dev/import` — Referencia de API

Namespace `Arqel\Import\`. Pipeline de importación CSV/XLSX: columnas declarativas, procesamiento troceado y transaccional, descarga en CSV de las filas fallidas.

## `Arqel\Import\Importer` (abstract)

Clase base que una app consumidora extiende para declarar una importación.

| Método | Tipo | Descripción |
|---|---|---|
| `$model` | `static class-string<Model>` | Modelo Eloquent de destino |
| `columns()` | `array<ImportColumn>` (abstract) | Descriptores de columna — declárala |
| `resolveRecord(array $data)` | `Model` | Resuelve el modelo al que se mapea una fila validada. Por defecto: `new $model` (inserción). Sobrescríbelo para hacer upsert, p. ej. `User::firstOrNew(['email' => $data['email']])` |
| `rules()` | `array<string, array>` | Reglas de validación indexadas por nombre de columna, derivadas de `columns()` |

## `Arqel\Import\ImportColumn` (final)

Descriptor declarativo de columna. Factory: `ImportColumn::make($name)` — `$name` coincide con la cabecera del archivo.

| Método | Tipo | Descripción |
|---|---|---|
| `label(string)` | `self` | Etiqueta visible (por defecto = nombre) |
| `rules(array)` | `self` | Reglas de validación de Laravel aplicadas por fila |
| `fillUsing(Closure)` | `self` | Transforma el valor bruto de la celda antes de la validación |
| `requiredMapping(bool = true)` | `self` | Marca la cabecera como obligatoria — una cabecera ausente aborta el job con un error de configuración en lugar de dejar silenciosamente en null todas las filas |
| `getName()` / `getLabel()` / `getRules()` / `isMappingRequired()` | getters | |
| `applyFill(?string $raw)` | `mixed` | Ejecuta el `fillUsing` configurado (o devuelve `$raw` sin cambios) |

## `Arqel\Import\ImportFormat` (enum, respaldado por string)

Casos: `CSV`, `XLSX`.

| Método | Tipo | Descripción |
|---|---|---|
| `extension()` | `string` | Valor del enum (`'csv'`, `'xlsx'`) |
| `fromExtension(string)` | `self` (static) | Lanza `InvalidArgumentException` ante extensiones no soportadas |

## `Arqel\Import\Contracts\FileReader` (interfaz)

`read(string $source): iterable<int, array<string, string|null>>` — transmite las filas de forma perezosa, indexadas por cabecera, sin cargar nunca el archivo entero en memoria. Implementaciones: `Readers\CsvReader`, `Readers\XlsxReader` (ambas respaldadas por `spatie/simple-excel`; XLSX requiere además `ext-zip`).

## `Arqel\Import\Contracts\ImportLogger` (interfaz)

Hook de ciclo de vida y progreso.

| Método | Descripción |
|---|---|
| `logQueued(string $importId, ImportFormat $format)` | Job despachado |
| `progress(string $importId, int $imported, int $skipped)` | Se llama después de cada trozo |
| `logCompleted(string $importId, int $imported, int $skipped, ?string $failedRowsPath)` | Job finalizado |
| `logFailed(string $importId, ImportFormat $format, Throwable $exception)` | El job lanzó una excepción |

Binding por defecto: `Arqel\Import\Logging\NullImportLogger` (sin operación), vinculado vía `singletonIf`. Las apps lo sobrescriben para persistir una tabla `imports` o notificar a los usuarios.

## `Arqel\Import\Jobs\ProcessImportJob` (final, `implements ShouldQueue`)

Transmite el archivo de origen en trozos de 100 filas, cada uno dentro de su propia `DB::transaction()`. Valida cada fila mediante `Validator::make($data, $rules)`; las filas fallidas se recopilan (con una columna sintética `_errors`) en lugar de abortar el job, y se escriben al final en un CSV descargable.

| Parámetro del constructor | Tipo | Descripción |
|---|---|---|
| `$importId` | `string` | Correlaciona las llamadas de progreso y logging |
| `$format` | `ImportFormat` | |
| `$importerClass` | `class-string<Importer>` | |
| `$sourcePath` | `string` | Ruta absoluta del archivo subido |
| `$failedRowsDir` | `?string` | Por defecto `storage_path('app/arqel-imports')` cuando es `null` |

`handle(ImportLogger $logger): void` es el punto de entrada (Laravel resuelve `$logger` desde el contenedor). Las celdas del CSV de filas fallidas se sanean contra la inyección de fórmulas CSV (un `= + - @` inicial o un carácter de control recibe un apóstrofo como prefijo).

## `Arqel\Import\Actions\ImportAction` (final, `extends Arqel\Actions\Action`)

Action de toolbar que abre el flujo de subida para importar en un Resource. Al extender la `Action` del framework hereda la autorización por action en todos los puntos de entrada.

| Método | Tipo | Descripción |
|---|---|---|
| `ImportAction::make(string $name)` | `static` | Factory. Establece la etiqueta `arqel-import::import.action` y el icono `upload` |
| `importer(class-string<Importer>)` | `self` | |
| `format(ImportFormat)` | `self` | Por defecto `ImportFormat::CSV` |
| `getImporterClass()` / `getFormat()` | getters | |

## HTTP

Registrado en `routes/admin.php` bajo `web` + `auth` (sin más autorización incluida — las apps lo envuelven con su propio gate):

| Verbo | Ruta | Nombre | Controlador |
|---|---|---|---|
| POST | `admin/imports` | `arqel.imports.upload` | `Http\Controllers\ImportUploadController` |
| GET | `admin/imports/{importId}/failed-rows` | `arqel.imports.failed-rows` | `Http\Controllers\FailedRowsDownloadController` |

## Ejemplo

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

## Relacionado

- SKILL: [`packages/import/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/import/SKILL.md)
- Código fuente: [`packages/import/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/import/src/)
- Paquete hermano: [`arqel-dev/export`](/es/reference/php/export) (misma postura de streaming + autorización en el borde)
