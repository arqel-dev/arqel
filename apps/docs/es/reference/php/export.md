# `arqel-dev/export` — Referencia de API

Namespace `Arqel\Export\`. Pipeline de exportación: CSV/XLSX/PDF, exportadores en streaming, una `Action` en lote preconfigurada y un job asíncrono para conjuntos de datos grandes.

## `Arqel\Export\ExportFormat` (enum, respaldado por string)

Casos: `CSV`, `XLSX`, `PDF`.

| Método | Tipo | Descripción |
|---|---|---|
| `mimeType()` | `string` | Tipo MIME de la IANA para las cabeceras `Content-Type` |
| `extension()` | `string` | Valor del enum, sin punto inicial (`'csv'`, `'xlsx'`, `'pdf'`) |

## `Arqel\Export\Contracts\Exporter` (interfaz)

`export(iterable $rows, array<array<string, mixed>> $columns, string $destination): string` — escribe el archivo en la ruta absoluta `$destination` y devuelve la ruta escrita. Las implementaciones son sin estado.

| Implementación | Respaldo | Notas |
|---|---|---|
| `Exporters\CsvExporter` (final) | `spatie/simple-excel` | En streaming, BOM UTF-8 por defecto, saneado contra inyección de fórmulas CSV |
| `Exporters\XlsxExporter` (final) | `spatie/simple-excel` (OpenSpout) | En streaming; las fechas se escriben como cadenas formateadas, no como seriales de Excel |
| `Exporters\PdfExporter` (final) | `dompdf/dompdf` | Renderiza un `<table>` HTML mínimo (sin dependencia de Blade); setters fluidos `setOrientation(string)` / `setPaperSize(string)`, con valores por defecto `'portrait'`/`'a4'` |

Los tres implementan una convención compartida de `formatCell()` (vía `Exporters\FormatsDateCells`): `date` → cadena formateada respetando las props `mode`/`format` de la columna, `boolean` → `Yes`/`No`, `relationship` → resuelto vía `display_path`, con `(string) $value` como respaldo (`null` → `''`).

Cada exportador expone además un helper `static streamDownload(iterable $rows, array $columns, string $filename): StreamedResponse` para descargas HTTP pequeñas y síncronas sin escribir antes en disco — los conjuntos de datos grandes deberían pasar por el pipeline asíncrono (`ExportAction` + `ProcessExportJob`) en su lugar.

## `Arqel\Export\Contracts\RecordsResolver` (interfaz)

`resolve(): iterable` — resuelve el conjunto de registros de una exportación encolada. `ProcessExportJob` almacena solo el FQCN del resolver en el payload del job (no la colección en sí), por lo que las implementaciones deberían devolver una fuente en streaming (lazy collection, generador, cursor de Eloquent).

## `Arqel\Export\Contracts\ExportLogger` (interfaz)

| Método | Descripción |
|---|---|
| `logQueued(string $exportId, ExportFormat $format)` | |
| `logCompleted(string $exportId, string $path, ExportFormat $format)` | |
| `logFailed(string $exportId, ExportFormat $format, Throwable $exception)` | |

Binding por defecto: `Logging\NullExportLogger` (sin operación), vinculado vía `singletonIf`. Las apps lo sobrescriben para persistir una tabla `exports` o notificar a los usuarios.

## `Arqel\Export\Actions\ExportAction` (final, `extends Arqel\Actions\Action`)

Bulk action preconfigurada que exporta la selección actual. Extiende `Action` directamente (no `BulkAction`, que es `final`) y emite `type = 'bulk'` para que los consumidores la traten de forma idéntica.

| Método | Tipo | Descripción |
|---|---|---|
| `ExportAction::make(string $name)` | `static` | Factory. Establece la etiqueta `arqel::actions.export`, el icono `download` y el `destinationDir` por defecto (config `arqel-export.destination_dir` o `storage_path('app/arqel-exports')`) |
| `format(ExportFormat)` | `self` | Por defecto `ExportFormat::CSV` |
| `getFormat()` | `ExportFormat` | |
| `withColumns(array<array<string, mixed>>)` | `self` | Descriptores de columna entregados al exportador |
| `withDestinationDir(string)` | `self` | Sobrescribe el directorio de salida |
| `dryRun(bool = true)` | `self` | Omite la llamada real al exportador; `execute()` devuelve `path => 'dry-run'` |
| `execute(mixed $record = null, array $data = [])` | `array{path, filename, format, mimeType}` | `$record` debe ser un `iterable`/`Traversable`, de lo contrario lanza `InvalidArgumentException`. Escribe `export-<uuid>.<ext>` dentro del directorio de destino |

## `Arqel\Export\Jobs\ProcessExportJob` (final, `implements ShouldQueue`)

Escribe el formato elegido en `<destinationDir>/export-<exportId>.<ext>`.

| Parámetro del constructor | Tipo | Descripción |
|---|---|---|
| `$exportId` | `string` | |
| `$format` | `ExportFormat` | |
| `$columns` | `array<array<string, mixed>>` | |
| `$recordsResolverClass` | `class-string<RecordsResolver>` | Se resuelve desde el contenedor en el momento de `handle()` |
| `$destinationDir` | `?string` | Por defecto `storage_path('app/arqel-exports')` cuando es `null` |

`handle(ExportLogger $logger): void` es el punto de entrada. Si tiene éxito llama a `logCompleted`; ante cualquier `Throwable`, llama a `logFailed` y vuelve a lanzarlo.

## HTTP

Registrado en `routes/admin.php` bajo `web` + `auth` (**sin autorización incluida** — los consumidores lo envuelven con su propio middleware):

| Verbo | Ruta | Nombre | Controlador |
|---|---|---|---|
| GET | `/admin/exports/{exportId}/download` | `arqel.export.download` | `Http\Controllers\ExportDownloadController::download` |

`{exportId}` está restringido a `[a-f0-9-]+`. El controlador hace glob sobre `<dir>/export-{exportId}.*`, abortando con 400 ante un id inválido o con 404 si hay cero o múltiples coincidencias; el `Content-Type` se deriva de `ExportFormat::tryFrom(...)?->mimeType()`.

## Ejemplo

```php
use Arqel\Export\Actions\ExportAction;
use Arqel\Export\ExportFormat;

ExportAction::make('export')
    ->format(ExportFormat::XLSX);
```

```php
use Arqel\Export\Exporters\CsvExporter;

// Descarga síncrona desde un controlador, conjunto de datos pequeño:
return CsvExporter::streamDownload($rows, $columns, 'users.csv');
```

## Relacionado

- SKILL: [`packages/export/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/export/SKILL.md)
- Código fuente: [`packages/export/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/export/src/)
- Paquete hermano: [`arqel-dev/import`](/es/reference/php/import) (misma postura de streaming + autorización en el borde)
