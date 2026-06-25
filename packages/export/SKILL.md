# SKILL.md — arqel-dev/export

> Contexto canônico para AI agents.

## Purpose

`arqel-dev/export` entrega a pipeline de exportação do Arqel — converte a seleção de uma `Table` (ou um dataset arbitrário) em arquivos CSV, XLSX ou PDF. Cobre RF-T-14. Os três exporters (CSV/XLSX/PDF) estão totalmente implementados; `spatie/simple-excel` e `dompdf/dompdf` são dependências hard `require` em `composer.json` (não `suggest:`).

## Status

**Entregue (EXPORT-001):**

- Esqueleto do pacote `arqel-dev/export` com PSR-4 `Arqel\Export\` → `src/`, deps em `arqel-dev/core` e `arqel-dev/actions` via path repo
- **`Arqel\Export\ExportFormat`** — enum `string` com casos `CSV`/`XLSX`/`PDF` + métodos `mimeType(): string` e `extension(): string`. Single source of truth para Content-Type headers e filenames
- **`Arqel\Export\Contracts\Exporter`** — interface `export(iterable $rows, array $columns, string $destination): string` (retorna o path escrito)
- **`Arqel\Export\Exporters\XlsxExporter|PdfExporter`** — `final class` implementando `Exporter`. Bodies lançam `RuntimeException` apontando para EXPORT-003/004 (CsvExporter já real — ver EXPORT-002 abaixo)
- **`Arqel\Export\Actions\ExportAction`** — `final` action bulk pré-configurada com label `'Export'` + icon `'download'`. Factory `make(string $name = 'export')`, fluent `format(ExportFormat)` + getter `getFormat()`. `execute()` lança `RuntimeException("Wired in EXPORT-005")` (stub posture). Detalhe técnico: a spec original do ticket pede `extends BulkAction`, mas `Arqel\Actions\Types\BulkAction` é `final`. Como o ticket proíbe modificar outros pacotes, `ExportAction` estende `Arqel\Actions\Action` directamente e emite `type = 'bulk'` — consumidores tratam-na como BulkAction sem nenhuma diferença observável no payload Inertia. Chunking + `deselectRecordsAfterCompletion` voltam quando a wiring real chegar em EXPORT-005
- **`Arqel\Export\ExportServiceProvider`** auto-discovered via `extra.laravel.providers` (extends `Spatie\LaravelPackageTools\PackageServiceProvider`). Sem migrations, sem config, sem routes — todos esses ficam em tickets posteriores
- Tests Pest cobrindo enum, contract stubs, ExportAction defaults + fluent setter, ServiceProvider boot

**Entregue (EXPORT-002):**

- **`Arqel\Export\Exporters\CsvExporter`** — implementação real backed por `spatie/simple-excel` (`SimpleExcelWriter::create($destination)`). Header derivado de `column['label'] ?? column['name']`; cells formatadas por `formatCell()` com handling explícito para `date` (`Y-m-d` quando `DateTimeInterface`), `boolean` (`Yes`/`No`), `relationship` (segue `display_path`) e fallback `(string) $value` (null → `''`). Streaming row-by-row, sem `->all()`/`->get()` — memory constante mesmo em datasets grandes. UTF-8 BOM ligado por default (Excel-on-Windows)
- **`CsvExporter::streamDownload(iterable $rows, array $columns, string $filename): StreamedResponse`** — helper estático `static` para o caminho HTTP sync. Devolve um `Symfony\Component\HttpFoundation\StreamedResponse` com `Content-Type: text/csv; charset=UTF-8` + `Content-Disposition: attachment` que invoca `SimpleExcelWriter::streamDownload()` dentro do callback. Mantém o contrato file-based (`export()`) intacto — é apenas um conveniência para downloads sync de datasets pequenos. Datasets grandes continuam a passar pelo pipeline async (`ExportAction` + `ProcessExportJob`, EXPORT-005+)
- **`spatie/simple-excel: ^3.0`** promovido de `suggest` para `require` (deixa de ser opcional para o pacote — apps que não exportam continuam a poder excluir manualmente). `dompdf/dompdf` continua em `suggest` até EXPORT-004
- Pest tests `tests/Unit/CsvExporterTest.php` cobrindo: header+rows + return value, empty iterable (só header), boolean → Yes/No, date → `Y-m-d`, relationship → `display_path`, fallback de label, null cell em row mista. `ExportersTest` mantém apenas as asserções de RuntimeException para XLSX/PDF (CSV deixou de lançar)

**Entregue (EXPORT-003):**

- **`Arqel\Export\Exporters\XlsxExporter`** — implementação real backed por `spatie/simple-excel` (`SimpleExcelWriter::create($destination)`; OpenSpout under the hood). Mesma estrutura do `CsvExporter` (header derivado de `column['label'] ?? column['name']`, streaming row-by-row, contrato `export(iterable $rows, array $columns, string $destination): string`) e o mesmo `formatCell()`: `date` → `Y-m-d` quando `DateTimeInterface` (espelhando `CsvExporter`/`PdfExporter`), `boolean` (`Yes`/`No`), `relationship` (`display_path` → `data_get`), fallback `(string) $value` (null → `''`). Passar o `DateTimeInterface` cru fazia o OpenSpout escrever o **serial do Excel** sob o formato General (sem `numFmt` de data anexado), então Excel/LibreOffice mostravam o número literal, não uma data (#106) — daí a formatação para string
- **`XlsxExporter::streamDownload(iterable $rows, array $columns, string $filename): StreamedResponse`** — helper estático mirror do `CsvExporter::streamDownload`, mas com `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` e usando `SimpleExcelWriter::streamDownload($filename)` (SimpleExcel infere o formato pela extensão do filename). Contrato file-based intacto
- Trade-off documentado: frozen header row + auto column widths ficam de fora — `spatie/simple-excel` v3 não expõe helpers first-class e mexer em internals do OpenSpout introduz acoplamento frágil. `// TODO(EXPORT-XXX)` comment no código se um ticket futuro decidir adicionar
- Pest tests `tests/Unit/XlsxExporterTest.php` (6 cenários): a maioria usa **round-trip read** via `SimpleExcelReader::create($path)->noHeaderRow()->getRows()` (header+rows, empty iterable, boolean → Yes/No, relationship `display_path`, fallback de label). O cenário de **data** inspeciona o **XML cru** da worksheet (`xl/worksheets/sheet1.xml`, via `ZipArchive`) e assere o texto `Y-m-d` — não o reader, que reconstruiria um `DateTime` a partir do serial e mascararia o #106. `ExportersTest` deixou de asserir RuntimeException para XLSX — só PDF ainda stub

**Entregue (EXPORT-004):**

- **`Arqel\Export\Exporters\PdfExporter`** — implementação real backed por `dompdf/dompdf`. Renderiza um HTML mínimo (`<table>` simples com styling inline, font default `DejaVu Sans` para suportar Unicode sem registrar fonts custom) e passa pelo `Dompdf::loadHtml()` + `setPaper()` + `render()`; o output é escrito em `$destination` via `file_put_contents()`. Não há dependência em Blade neste ticket — o template default é uma string PHP — para manter o footprint do pacote pequeno. Override via Blade (`Resource::pdfView()`) chega em EXPORT-005
- **`PdfExporter::setOrientation(string $orientation): static`** + **`setPaperSize(string $size): static`** — fluent setters aplicados em cada `render()`. Defaults `'portrait'` / `'a4'`. Aceitam qualquer string que dompdf entenda (`'landscape'`, `'letter'`, `'legal'`, etc.)
- **`PdfExporter::streamDownload(iterable $rows, array $columns, string $filename): StreamedResponse`** — helper estático mirror do CSV/XLSX para downloads sync. Renderiza para memória e devolve `Content-Type: application/pdf`. Em datasets grandes, continuar a usar o pipeline async (`ExportAction` + `ProcessExportJob`, EXPORT-005+)
- `formatCell()` espelha o do `CsvExporter` — sempre stringifica (`date` → `Y-m-d`, `boolean` → `Yes`/`No`, `relationship` → `data_get($record, $display_path ?? $name)`, fallback `(string) $value` com null → `''`). Toda saída passa por `htmlspecialchars()` antes de ir para o HTML para evitar quebra de layout
- **`dompdf/dompdf: ^3.0`** promovido de `suggest` para `require` — deixa de ser opcional. Apps que não exportam PDF continuam a poder excluir manualmente via `replace`/`exclude-from-classmap` se quiserem
- Pest tests `tests/Unit/PdfExporterTest.php` (8 cenários) com guard `markTestSkipped` se `Dompdf\Dompdf` ou `ext-mbstring` não estiverem disponíveis. Cobertura: happy path com assertion dos 4 bytes mágicos `%PDF`, empty rows ainda gera PDF válido, `setOrientation`/`setPaperSize` fluentes e persistentes (via reflexão na property privada), `formatCell` para boolean/date/relationship/scalar (também via reflexão — mais barato que parsear o PDF). `ExportersTest` deixou de asserir RuntimeException — todos os 3 exporters são reais agora

**Entregue (EXPORT-005 — escopo reduzido):**

- **`Arqel\Export\Actions\ExportAction::execute(mixed $record, array $data)`** wired pela primeira vez. Resolve o `Exporter` correto a partir de `$this->format` (`CsvExporter`/`XlsxExporter`/`PdfExporter`), constrói filename `'export-' . date('Ymd-His') . '.' . $format->extension()`, escreve em `rtrim($destinationDir, '/') . '/' . $filename` chamando `$exporter->export($record, $columns, $destination)`, e devolve `['path' => ..., 'filename' => ..., 'format' => $format->value, 'mimeType' => $format->mimeType()]`. `$record` é a `Collection|Traversable|iterable` que o pipeline `BulkAction` passa; scalar/null lança `InvalidArgumentException`
- **`withColumns(array)`**, **`withDestinationDir(string)`**, **`dryRun(bool=true)`** — fluent setters. `dryRun` bypassa exporter e devolve `['path' => 'dry-run', ...]` para tests + previews
- Pest tests `tests/Unit/ExportActionExecuteTest.php` (9 cenários)
- **Form modal + queue threshold dispatch + signed URLs** deferred para EXPORT-006/008+
- **Dispatch sync no core (#48):** `ResourceController::bulkAction()` corre qualquer bulk action encontrada via `execute($records, $data)` — não exige callback. Como `ExportAction` faz override de `execute()` sem nunca chamar `->action()`, `hasCallback()` é `false`; o dispatcher antigo no-opava com flash de erro. Agora só o stock `delete` (sem callback) mantém o fast-path de DB; tudo o resto passa por `execute()`. A `url` serializada da action aponta para `POST /admin/{slug}/bulk/{name}` (stock url de bulk em `arqel-dev/actions`), portanto o botão de bulk no frontend invoca a rota real registada

**Entregue (EXPORT-006 — escopo reduzido):**

- **`Arqel\Export\Jobs\ProcessExportJob`** — `final class implements ShouldQueue` (uses `Dispatchable`, `InteractsWithQueue`, `Queueable`, `SerializesModels`). Construtor com props readonly: `string $exportId` (UUID injectado pelo caller), `ExportFormat $format`, `array $columns`, `class-string<RecordsResolver> $recordsResolverClass` e `?string $destinationDir = null`. `handle(ExportLogger $logger): void` resolve o resolver via container (`app($recordsResolverClass)`), valida `instanceof RecordsResolver`, escolhe o exporter por `match($format)`, garante o diretório (`mkdir` recursivo) e escreve `<dir>/export-<exportId>.<ext>`. Em sucesso chama `$logger->logCompleted(...)`; em qualquer `Throwable` chama `$logger->logFailed(...)` e re-lança
- **`Arqel\Export\Contracts\RecordsResolver`** — interface single-method `resolve(): iterable`. **Trade-off chave:** o job armazena apenas a FQCN, NÃO a coleção serializada — evita payloads de fila gigantes
- **`Arqel\Export\Contracts\ExportLogger`** — interface lifecycle (`logQueued`, `logCompleted`, `logFailed`). Default binding `NullExportLogger` via `singletonIf` — apps consumidoras sobrescrevem
- **`Arqel\Export\Http\Controllers\ExportDownloadController`** — `download(string $exportId, Request)` faz `glob('<dir>/export-{exportId}.*')`, abort 400 UUID inválido, 404 se 0 ou >1 matches. Content-Type via `ExportFormat::tryFrom(...)?->mimeType()`. **Sem auth check** — consumer wraps com middleware própria
- **Rota** `routes/admin.php` → `GET /admin/exports/{exportId}/download` (name `arqel.export.download`, where `[a-f0-9-]+`)
- Tests: 6 ProcessExportJob + 4 ExportDownloadController + 1 ServiceProvider binding

**Corrigido (#67 — bulk export coerente end-to-end):**

- **Colunas finalmente ligadas (A):** `ResourceController::bulkAction()` agora serializa `Table::getColumns()` (objetos `Column` → `toArray()`; descritores já-array passam through) e chama `withColumns()` na bulk action quando esta o suporta. Antes, `ExportAction` recebia `columns = []` e o `CsvExporter` escrevia só o BOM (`\xEF\xBB\xBF\n\n`). Agora o CSV tem headers reais + uma linha por registro selecionado. Wiring duck-typed — core não ganha dep em `arqel-dev/table`/`arqel-dev/export`
- **Arquivo retrievable (B):** `ExportAction::make()` passa a default `destinationDir` para `storage_path('app/arqel-exports')` (o diretório que o `ExportDownloadController` lê; respeita `config('arqel-export.destination_dir')`), `execute()` garante o diretório (`mkdir` recursivo) e o filename muda de `export-<Ymd-His>` para `export-<uuid>` — o UUID casa o route constraint `[a-f0-9-]+` + glob do controller e elimina colisões no mesmo segundo. `ResourceController::bulkAction()` deixa de descartar o retorno de `execute()`: extrai o `exportId` do filename e faz flash de `download_url` (`route('arqel.export.download')`) quando a rota está registrada
- Tests: `packages/core/tests/Feature/BulkActionColumnInjectionTest.php` (forward de colunas) + `packages/export/tests/Feature/BulkExportRoundTripTest.php` (CSV populado + URL de download via o seam real controller→action→exporter)
- **Ainda deferred (EXPORT-007/008+):** o flash-notification completo + **signed URL** com `Export` model (ownership + expiry) + queue threshold. O `download_url` flashado hoje é não-assinado e a rota não impõe authorization (consumer wraps com middleware — ver docblock do `ExportDownloadController`)

**Por chegar (EXPORT-007..010):**

- Override de template Blade (`Resource::pdfView()` + `pdfOrientation()`) — EXPORT-007
- Cleanup scheduler (`exports:prune`) — EXPORT-008
- Signed URLs + ownership policy bundled — EXPORT-009
- Suite full + SKILL.md final — EXPORT-010

## Conventions

- `declare(strict_types=1)` obrigatório
- As libs de export (simple-excel, dompdf) foram promovidas de `suggest:` para `require` à medida que cada exporter foi implementado — hoje as três são dependências unconditional do pacote. Apps que não exportam um formato podem excluí-lo manualmente via `replace`/`exclude-from-classmap`
- `Exporter::export()` recebe `$destination` absoluto (já resolvido pelo caller via `storage_path()` ou disk-aware path); o exporter só escreve, não decide localização
- `ExportFormat::extension()` devolve sem ponto inicial (`'csv'`, não `'.csv'`); o ponto é responsabilidade do construtor de filename
- **Células de data honram o `mode`/`format` da `DateColumn`** (#217) via o trait compartilhado `FormatsDateCells::formatDateCell()`, usado pelos três exporters nos branches plano e `state_resolver`: `props.mode` (`date`|`datetime`|`since`) + `props.format` decidem o output, espelhando o que a tabela renderiza — `->dateTime()` retém a hora, `->date('d/m/Y')` aplica o formato custom, e `since` emite a string relativa (`diffForHumans()`). Default sem props continua `Y-m-d`. O XLSX mantém a data como **string** (não serial Excel, #106), só com o formato correto

## Anti-patterns

- ❌ **Carregar dataset inteiro em memória** — todos os exporters reais são streaming (`iterable`/generator). Nunca `->all()` ou `->get()` antes de passar ao exporter
- ❌ **Hardcoded paths** dentro do exporter — `$destination` é injectado, não derivado de `storage_path()` no exporter
- ❌ **Bypass do `ExportAction`** para downloads sync — em datasets grandes (>1k rows) gera timeout. Use sempre o pipeline async (`ProcessExportJob` + signed URL) que chega em EXPORT-005..008
- ❌ **Estender `BulkAction`** para nova action de export custom — `BulkAction` é `final`. Estenda `ExportAction` ou `Action` directamente

## Related

- Tickets: [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §EXPORT-001..010
- Source: [`packages/export/src/`](./src/)
- Tests: [`packages/export/tests/`](./tests/)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only (downloads são out-of-band, fora do Inertia visit)
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
