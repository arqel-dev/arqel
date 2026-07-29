# `arqel-dev/export` — Referência de API

Namespace `Arqel\Export\`. Pipeline de exportação: CSV/XLSX/PDF, exporters com streaming, uma `Action` em lote pré-configurada e um job assíncrono para grandes volumes de dados.

## `Arqel\Export\ExportFormat` (enum, backed por string)

Casos: `CSV`, `XLSX`, `PDF`.

| Método | Tipo | Descrição |
|---|---|---|
| `mimeType()` | `string` | Mime type IANA para headers `Content-Type` |
| `extension()` | `string` | Valor do enum, sem ponto inicial (`'csv'`, `'xlsx'`, `'pdf'`) |

## `Arqel\Export\Contracts\Exporter` (interface)

`export(iterable $rows, array<array<string, mixed>> $columns, string $destination): string` — escreve o arquivo no caminho absoluto `$destination` e retorna o caminho escrito. As implementações são stateless.

| Implementação | Base | Notas |
|---|---|---|
| `Exporters\CsvExporter` (final) | `spatie/simple-excel` | Streaming, BOM UTF-8 por default, sanitizado contra CSV formula injection |
| `Exporters\XlsxExporter` (final) | `spatie/simple-excel` (OpenSpout) | Streaming; datas escritas como strings formatadas, não como seriais do Excel |
| `Exporters\PdfExporter` (final) | `dompdf/dompdf` | Renderiza um `<table>` HTML mínimo (sem dependência de Blade); setters fluentes `setOrientation(string)` / `setPaperSize(string)`, defaults `'portrait'`/`'a4'` |

Os três implementam uma convenção compartilhada de `formatCell()` (via `Exporters\FormatsDateCells`): `date` → string formatada respeitando as props `mode`/`format` da column, `boolean` → `Yes`/`No`, `relationship` → resolvido via `display_path`, fallback `(string) $value` (`null` → `''`).

Cada exporter também expõe um helper `static streamDownload(iterable $rows, array $columns, string $filename): StreamedResponse` para downloads HTTP síncronos e pequenos, sem escrever em disco antes — volumes grandes devem passar pelo pipeline assíncrono (`ExportAction` + `ProcessExportJob`).

## `Arqel\Export\Contracts\RecordsResolver` (interface)

`resolve(): iterable` — resolve o conjunto de records de uma exportação enfileirada. O `ProcessExportJob` armazena apenas o FQCN do resolver no payload do job (não a collection em si), então as implementações devem retornar uma fonte com streaming (lazy collection, generator, cursor do Eloquent).

## `Arqel\Export\Contracts\ExportLogger` (interface)

| Método | Descrição |
|---|---|
| `logQueued(string $exportId, ExportFormat $format)` | |
| `logCompleted(string $exportId, string $path, ExportFormat $format)` | |
| `logFailed(string $exportId, ExportFormat $format, Throwable $exception)` | |

Binding default: `Logging\NullExportLogger` (no-op), registrado via `singletonIf`. As aplicações sobrescrevem para persistir uma tabela `exports` e/ou notificar usuários.

## `Arqel\Export\Actions\ExportAction` (final, `extends Arqel\Actions\Action`)

Action em lote pré-configurada que exporta a seleção atual. Estende `Action` diretamente (e não `BulkAction`, que é `final`) e emite `type = 'bulk'` para que os consumidores a tratem de forma idêntica.

| Método | Tipo | Descrição |
|---|---|---|
| `ExportAction::make(string $name)` | `static` | Factory. Define o label `arqel::actions.export`, o ícone `download` e o `destinationDir` default (config `arqel-export.destination_dir` ou `storage_path('app/arqel-exports')`) |
| `format(ExportFormat)` | `self` | Default `ExportFormat::CSV` |
| `getFormat()` | `ExportFormat` | |
| `withColumns(array<array<string, mixed>>)` | `self` | Descritores de column entregues ao exporter |
| `withDestinationDir(string)` | `self` | Sobrescreve o diretório de saída |
| `dryRun(bool = true)` | `self` | Pula a chamada real ao exporter; `execute()` retorna `path => 'dry-run'` |
| `execute(mixed $record = null, array $data = [])` | `array{path, filename, format, mimeType}` | `$record` precisa ser um `iterable`/`Traversable`, caso contrário lança `InvalidArgumentException`. Escreve `export-<uuid>.<ext>` dentro do diretório de destino |

## `Arqel\Export\Jobs\ProcessExportJob` (final, `implements ShouldQueue`)

Escreve o formato escolhido em `<destinationDir>/export-<exportId>.<ext>`.

| Parâmetro do construtor | Tipo | Descrição |
|---|---|---|
| `$exportId` | `string` | |
| `$format` | `ExportFormat` | |
| `$columns` | `array<array<string, mixed>>` | |
| `$recordsResolverClass` | `class-string<RecordsResolver>` | Resolvido pelo container no momento do `handle()` |
| `$destinationDir` | `?string` | Default `storage_path('app/arqel-exports')` quando `null` |

`handle(ExportLogger $logger): void` é o ponto de entrada. Em caso de sucesso chama `logCompleted`; em qualquer `Throwable`, chama `logFailed` e relança a exceção.

## HTTP

Registrado em `routes/admin.php` sob `web` + `auth` (**sem autorização embutida** — os consumidores envolvem com o próprio middleware):

| Verbo | Route | Nome | Controller |
|---|---|---|---|
| GET | `/admin/exports/{exportId}/download` | `arqel.export.download` | `Http\Controllers\ExportDownloadController::download` |

`{exportId}` é restrito a `[a-f0-9-]+`. O controller faz glob em `<dir>/export-{exportId}.*`, abortando com 400 em um id inválido ou 404 quando há zero ou múltiplas correspondências; o `Content-Type` é derivado de `ExportFormat::tryFrom(...)?->mimeType()`.

## Exemplo

```php
use Arqel\Export\Actions\ExportAction;
use Arqel\Export\ExportFormat;

ExportAction::make('export')
    ->format(ExportFormat::XLSX);
```

```php
use Arqel\Export\Exporters\CsvExporter;

// Download síncrono a partir de um controller, volume pequeno:
return CsvExporter::streamDownload($rows, $columns, 'users.csv');
```

## Relacionados

- SKILL: [`packages/export/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/export/SKILL.md)
- Código-fonte: [`packages/export/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/export/src/)
- Pacote irmão: [`arqel-dev/import`](/pt-BR/reference/php/import) (mesma postura de streaming + autorização na borda)
