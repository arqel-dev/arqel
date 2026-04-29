# SKILL.md — arqel/export

> Contexto canônico para AI agents.

## Purpose

`arqel/export` entrega a pipeline de exportação do Arqel — converte a seleção de uma `Table` (ou um dataset arbitrário) em arquivos CSV, XLSX ou PDF. Cobre RF-T-14. O pacote é só o esqueleto (interfaces + enum + stubs); as implementações reais ficam atrás de `suggest:` em `composer.json` para que panels que não exportam nada não precisem instalar `spatie/simple-excel` nem `dompdf/dompdf`.

## Status

**Entregue (EXPORT-001):**

- Esqueleto do pacote `arqel/export` com PSR-4 `Arqel\Export\` → `src/`, deps em `arqel/core` e `arqel/actions` via path repo
- **`Arqel\Export\ExportFormat`** — enum `string` com casos `CSV`/`XLSX`/`PDF` + métodos `mimeType(): string` e `extension(): string`. Single source of truth para Content-Type headers e filenames
- **`Arqel\Export\Contracts\Exporter`** — interface `export(iterable $rows, array $columns, string $destination): string` (retorna o path escrito)
- **`Arqel\Export\Exporters\CsvExporter|XlsxExporter|PdfExporter`** — três `final class` implementando `Exporter`. Bodies lançam `RuntimeException` apontando para EXPORT-002/003/004
- **`Arqel\Export\Actions\ExportAction`** — `final` action bulk pré-configurada com label `'Export'` + icon `'download'`. Factory `make(string $name = 'export')`, fluent `format(ExportFormat)` + getter `getFormat()`. `execute()` lança `RuntimeException("Wired in EXPORT-005")` (stub posture). Detalhe técnico: a spec original do ticket pede `extends BulkAction`, mas `Arqel\Actions\Types\BulkAction` é `final`. Como o ticket proíbe modificar outros pacotes, `ExportAction` estende `Arqel\Actions\Action` directamente e emite `type = 'bulk'` — consumidores tratam-na como BulkAction sem nenhuma diferença observável no payload Inertia. Chunking + `deselectRecordsAfterCompletion` voltam quando a wiring real chegar em EXPORT-005
- **`Arqel\Export\ExportServiceProvider`** auto-discovered via `extra.laravel.providers` (extends `Spatie\LaravelPackageTools\PackageServiceProvider`). Sem migrations, sem config, sem routes — todos esses ficam em tickets posteriores
- Tests Pest cobrindo enum, contract stubs, ExportAction defaults + fluent setter, ServiceProvider boot

**Por chegar (EXPORT-002..010):**

- `CsvExporter` real com `spatie/simple-excel` streaming + UTF-8 BOM (Excel-on-Windows compat) — EXPORT-002
- `XlsxExporter` com `spatie/simple-excel` writer — EXPORT-003
- `PdfExporter` com `dompdf/dompdf` (template Blade `export.pdf.blade.php`) — EXPORT-004
- `ExportAction::execute()` real — dispatcha `ProcessExportJob` e devolve notification + URL — EXPORT-005
- `Arqel\Export\Models\Export` + migration (`exports` table: `id`, `user_id`, `tenant_id`, `format`, `status`, `filename`, `mime_type`, `rows`, `bytes`, `disk`, `path`, `created_at`, `expires_at`) — EXPORT-006
- `Arqel\Export\Jobs\ProcessExportJob` (queueable, idempotent, persiste `Export` model) — EXPORT-007
- Download endpoint + signed URL helper — EXPORT-008
- Cleanup scheduler (`exports:prune`) — EXPORT-009
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
