# SKILL.md — arqel/export

> Contexto canônico para AI agents.

## Purpose

`arqel/export` entrega a pipeline de exportação do Arqel — converte a seleção de uma `Table` (ou um dataset arbitrário) em arquivos CSV, XLSX ou PDF. Cobre RF-T-14. O pacote é só o esqueleto (interfaces + enum + stubs); as implementações reais ficam atrás de `suggest:` em `composer.json` para que panels que não exportam nada não precisem instalar `spatie/simple-excel` nem `dompdf/dompdf`.

## Status

**Entregue (EXPORT-001):**

- Esqueleto do pacote `arqel/export` com PSR-4 `Arqel\Export\` → `src/`, deps em `arqel/core` e `arqel/actions` via path repo
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

- **`Arqel\Export\Exporters\XlsxExporter`** — implementação real backed por `spatie/simple-excel` (`SimpleExcelWriter::create($destination)`; OpenSpout under the hood). Mesma estrutura do `CsvExporter` (header derivado de `column['label'] ?? column['name']`, streaming row-by-row, contrato `export(iterable $rows, array $columns, string $destination): string`) com uma diferença chave: `formatCell()` **preserva tipos nativos quando útil para Excel** — `DateTimeInterface` flui inalterado (Excel renderiza como data real, não string `Y-m-d`); scalars passam through; só `boolean` (`Yes`/`No`) e `relationship` (`display_path` → `data_get`) são stringificados. Header row é negrito via `setHeaderStyle((new Style)->setFontBold())`
- **`XlsxExporter::streamDownload(iterable $rows, array $columns, string $filename): StreamedResponse`** — helper estático mirror do `CsvExporter::streamDownload`, mas com `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` e usando `SimpleExcelWriter::streamDownload($filename)` (SimpleExcel infere o formato pela extensão do filename). Contrato file-based intacto
- Trade-off documentado: frozen header row + auto column widths ficam de fora — `spatie/simple-excel` v3 não expõe helpers first-class e mexer em internals do OpenSpout introduz acoplamento frágil. `// TODO(EXPORT-XXX)` comment no código se um ticket futuro decidir adicionar
- Pest tests `tests/Unit/XlsxExporterTest.php` (6 cenários) com **round-trip read** via `SimpleExcelReader::create($path)->noHeaderRow()->getRows()` para asserir conteúdo (header+rows, empty iterable, boolean → Yes/No, **DateTime preservado** via assertion `instanceof DateTimeInterface`, relationship `display_path`, fallback de label). `ExportersTest` deixou de asserir RuntimeException para XLSX — só PDF ainda stub

**Entregue (EXPORT-004):**

- **`Arqel\Export\Exporters\PdfExporter`** — implementação real backed por `dompdf/dompdf`. Renderiza um HTML mínimo (`<table>` simples com styling inline, font default `DejaVu Sans` para suportar Unicode sem registrar fonts custom) e passa pelo `Dompdf::loadHtml()` + `setPaper()` + `render()`; o output é escrito em `$destination` via `file_put_contents()`. Não há dependência em Blade neste ticket — o template default é uma string PHP — para manter o footprint do pacote pequeno. Override via Blade (`Resource::pdfView()`) chega em EXPORT-005
- **`PdfExporter::setOrientation(string $orientation): static`** + **`setPaperSize(string $size): static`** — fluent setters aplicados em cada `render()`. Defaults `'portrait'` / `'a4'`. Aceitam qualquer string que dompdf entenda (`'landscape'`, `'letter'`, `'legal'`, etc.)
- **`PdfExporter::streamDownload(iterable $rows, array $columns, string $filename): StreamedResponse`** — helper estático mirror do CSV/XLSX para downloads sync. Renderiza para memória e devolve `Content-Type: application/pdf`. Em datasets grandes, continuar a usar o pipeline async (`ExportAction` + `ProcessExportJob`, EXPORT-005+)
- `formatCell()` espelha o do `CsvExporter` — sempre stringifica (`date` → `Y-m-d`, `boolean` → `Yes`/`No`, `relationship` → `data_get($record, $display_path ?? $name)`, fallback `(string) $value` com null → `''`). Toda saída passa por `htmlspecialchars()` antes de ir para o HTML para evitar quebra de layout
- **`dompdf/dompdf: ^3.0`** promovido de `suggest` para `require` — deixa de ser opcional. Apps que não exportam PDF continuam a poder excluir manualmente via `replace`/`exclude-from-classmap` se quiserem
- Pest tests `tests/Unit/PdfExporterTest.php` (8 cenários) com guard `markTestSkipped` se `Dompdf\Dompdf` ou `ext-mbstring` não estiverem disponíveis. Cobertura: happy path com assertion dos 4 bytes mágicos `%PDF`, empty rows ainda gera PDF válido, `setOrientation`/`setPaperSize` fluentes e persistentes (via reflexão na property privada), `formatCell` para boolean/date/relationship/scalar (também via reflexão — mais barato que parsear o PDF). `ExportersTest` deixou de asserir RuntimeException — todos os 3 exporters são reais agora

**Entregue (EXPORT-006 — escopo reduzido):**

- **`Arqel\Export\Jobs\ProcessExportJob`** — `final class implements ShouldQueue` (uses `Dispatchable`, `InteractsWithQueue`, `Queueable`, `SerializesModels`). Construtor com props readonly: `string $exportId` (UUID injectado pelo caller), `ExportFormat $format`, `array $columns`, `class-string<RecordsResolver> $recordsResolverClass` e `?string $destinationDir = null`. `handle(ExportLogger $logger): void` resolve o resolver via container (`app($recordsResolverClass)`), valida `instanceof RecordsResolver`, escolhe o exporter por `match($format)`, garante o diretório (`mkdir` recursivo se faltar — fallback `storage_path('app/arqel-exports')`) e escreve `<dir>/export-<exportId>.<ext>`. Em sucesso chama `$logger->logCompleted(...)`; em qualquer `Throwable` chama `$logger->logFailed(...)` e re-lança. Cleanup de arquivos antigos (>7 dias) ficou para um ticket futuro
- **`Arqel\Export\Contracts\RecordsResolver`** — interface single-method `resolve(): iterable`. **Trade-off chave:** o job armazena apenas a FQCN, NÃO a coleção serializada — evita payloads de fila gigantes em datasets grandes. Implementações devem devolver streaming (lazy collection, generator, Eloquent cursor)
- **`Arqel\Export\Contracts\ExportLogger`** — interface lifecycle (`logQueued`, `logCompleted`, `logFailed`). Default binding `Arqel\Export\Logging\NullExportLogger` via `singletonIf` no provider — apps consumidoras sobrescrevem para persistir tabela `exports` e/ou disparar Notifications. Mantém o pacote agnóstico de `User`, `Notification` e schema do `Export` model
- **`Arqel\Export\Http\Controllers\ExportDownloadController`** — `final class` com `download(string $exportId, Request $request): BinaryFileResponse`. Faz `glob('<dir>/export-{exportId}.*')`, aborta 400 em UUID inválido (regex `/^[a-f0-9-]+$/`), 404 se 0 ou >1 matches. Content-Type derivado da extensão via `ExportFormat::tryFrom(...)?->mimeType()`. **Sem auth check** — consumer apps DEVEM envolver com middleware de autorização própria (`auth` + `can:download-exports` ou similar). Diretório resolvido via config `arqel-export.destination_dir` com fallback `storage_path('app/arqel-exports')`
- **Rota** `routes/admin.php` → `GET /admin/exports/{exportId}/download` (name `arqel.export.download`, where `[a-f0-9-]+`) sob middleware `web` + `auth`. Wired no provider via `->hasRoute('admin')`
- **Out of scope (escopo reduzido para este ticket):**
  - User model + Notification dispatch + tabela `exports` + Export model — responsabilidade do consumer (wire via `ExportLogger` custom)
  - Loading de records via `Resource::$model::whereIn(...)` — substituído pelo padrão `RecordsResolver` que desacopla o job do Resource API
  - Cleanup scheduler (`exports:prune`) — adiado
  - Signed URLs — adiado (endpoint atual é cookie-auth + middleware do consumer)
- Tests: `Unit/Jobs/ProcessExportJobTest.php` (6 cenários — happy CSV, XLSX com guard ext-zip, PDF com guard `Dompdf`, criação recursiva de diretório, rejeição de classe que não implementa contrato, fallback null → `storage_path`), `Feature/ExportDownloadControllerTest.php` (4 cenários — happy 200, 400 UUID inválido, 404 not found, 404 ambíguo), `Feature/ExportServiceProviderBindingsTest.php` (1 — binding default = `NullExportLogger`)

**Por chegar (EXPORT-007..010):**

- `Arqel\Export\Models\Export` + migration (`exports` table) — fica no app consumer (e/ou ticket futuro do panel)
- Override de template Blade (`Resource::pdfView()` + `Resource::pdfOrientation()`) — EXPORT-007
- Cleanup scheduler (`exports:prune`) — EXPORT-008
- Signed URLs + ownership policy bundled — EXPORT-009
- Suite full + SKILL.md final — EXPORT-010

## Conventions

- `declare(strict_types=1)` obrigatório
- Hard deps em libs de export (simple-excel, dompdf) ficam em `suggest:` até serem efetivamente exigidas pelo exporter correspondente — apps que só usam CSV não pagam o custo de instalar dompdf
- `Exporter::export()` recebe `$destination` absoluto (já resolvido pelo caller via `storage_path()` ou disk-aware path); o exporter só escreve, não decide localização
- `ExportFormat::extension()` devolve sem ponto inicial (`'csv'`, não `'.csv'`); o ponto é responsabilidade do construtor de filename

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
