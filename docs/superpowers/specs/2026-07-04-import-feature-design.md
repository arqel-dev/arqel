# Design — Pacote `arqel/import` (Imports CSV/Excel)

> **Milestone 0.17** do roadmap-to-1.0. Lacuna competitiva #1 vs Filament/Nova
> (Export existe, Import não). Espelha a arquitetura de `packages/export`.
> Status: design aprovado (brainstorming 2026-07-04). Próximo: plano de implementação.

## Contexto

`packages/export` já entrega export 1ª classe (CSV/XLSX/PDF via `spatie/simple-excel`,
`ExportAction`, `ProcessExportJob` async, download controller). Não há contraparte de
importação: `arqel:*` não tem `Importer`, `ImportAction` nem `make:import`. Filament tem
Importer de 1ª classe com mapeamento de colunas, validação por-linha, jobs async e um CSV
de linhas falhadas. Este pacote fecha essa lacuna.

## Decisões de escopo (aprovadas)

1. **Formatos + execução:** CSV + XLSX, **async** (job na fila). Sem PDF (não faz sentido importar). Streaming via `spatie/simple-excel`.
2. **Erros por-linha:** **skip + failed-rows CSV** — linhas válidas importam, inválidas são puladas e coletadas num CSV de falhas para download (coluna `_errors`). Comportamento do Filament, robusto para arquivos grandes.
3. **Mapeamento:** **declarativo no PHP** via `ImportColumn` (casa por header do arquivo). Sem UI de mapeamento interativa no 0.17 (fase 2). Consistente com `ExportColumn` e o resto do framework.

## Arquitetura

Espelha `packages/export/src/` fielmente:

```
packages/import/src/
  Contracts/
    FileReader.php         # read(string $source): iterable — lazy, yield arrays por header
    ImportLogger.php       # progress()/finished() — espelha ExportLogger
  ImportFormat.php         # enum: CSV, XLSX (cada case → um FileReader)
  Readers/
    CsvReader.php          # implements FileReader; spatie/simple-excel streaming
    XlsxReader.php         # implements FileReader; spatie/simple-excel
  ImportColumn.php         # ::make()->label()->rules()->fillUsing()->requiredMapping()
  Importer.php             # classe-base abstrata do dev: columns(), resolveRecord(), $model
  Actions/
    ImportAction.php       # extends Action; ->importer()->format()
  Jobs/
    ProcessImportJob.php    # ShouldQueue; chunk + validação por-linha + failed-rows CSV
  Http/Controllers/
    ImportUploadController.php        # recebe upload, dispara job (autorizado)
    FailedRowsDownloadController.php  # baixa o CSV de falhas (autorizado)
  Logging/NullImportLogger.php
```

**Nota de nomenclatura:** dois conceitos distintos, nomes distintos para evitar colisão —
`FileReader` (interface do *leitor de formato*: `CsvReader`/`XlsxReader`) vs `Importer`
(classe-base abstrata que o **dev** estende, declarando `columns()`/`resolveRecord()`). No
Export o análogo do `FileReader` é `Contracts\Exporter`; aqui o nome é mais preciso porque o
leitor só lê o arquivo, enquanto o `Importer` do dev orquestra mapeamento+persistência.

**Fluxo:** upload → `ImportUploadController` valida arquivo + autoriza → guarda em storage →
despacha `ProcessImportJob` → job faz stream do arquivo em chunks → por linha: mapeia via
`ImportColumn` (aplica `fillUsing`) → valida (regras Laravel) → **válida**: `resolveRecord()` +
`fill()` + `save()`; **inválida**: acumula no CSV de falhas → ao fim, `ImportLogger` registra
contagens + path do failed-rows CSV.

### Contrato central `FileReader` (interface, espelha `Exporter`)

```php
interface FileReader
{
    /** Lê o arquivo e yield-a arrays associativos por header, lazy (streaming). */
    public function read(string $source): iterable;
}
```

## `ImportColumn`

```php
ImportColumn::make('email')              // header esperado (case/slug-insensitive)
    ->label('E-mail')                    // rótulo p/ mensagens de erro
    ->rules(['required', 'email'])       // regras Laravel, validadas por-linha
    ->fillUsing(fn (string $raw) => strtolower(trim($raw)))  // transforma valor cru
    ->requiredMapping()                  // header ausente → erro de setup (falha cedo), não por-linha
```

### Classe-base `Importer` (abstrata)

```php
final class UserImporter extends Importer
{
    public static string $model = User::class;

    public function columns(): array
    {
        return [
            ImportColumn::make('name')->rules(['required', 'string']),
            ImportColumn::make('email')->rules(['required', 'email', 'unique:users,email']),
        ];
    }

    // default: new static::$model (insert puro). Sobrescreve p/ upsert.
    public function resolveRecord(array $data): Model
    {
        return User::firstOrNew(['email' => $data['email']]);
    }
}
```

**Decisão de design:** `resolveRecord()` default retorna `new $model` (insert). O dev
sobrescreve para upsert (`firstOrNew`). Caso simples trivial, upsert possível — igual Filament.

## `ProcessImportJob`

Espelha `ProcessExportJob` (traits `Queueable`/`Dispatchable`/`SerializesModels`,
`implements ShouldQueue`).

**Entrada:** `importerClass`, `sourcePath`, `format` (ImportFormat), `userId`, `importId` (uuid).

**Loop (streaming, memory-safe):**
```
$importer = app($importerClass);
$reader   = $format === CSV ? new CsvReader : new XlsxReader;
$columns  = $importer->columns();

// 1. valida mapeamento (headers requiredMapping presentes) → falha cedo se faltar
// 2. stream lazy em chunks de N (default 100, configurável)
foreach (chunked($reader->read($sourcePath), $chunkSize) as $chunk) {
    DB::transaction(function () use ($chunk) {   // transação POR CHUNK
        foreach ($chunk as $raw) {
            $data = mapColumns($raw, $columns);          // aplica fillUsing
            $validator = Validator::make($data, rulesFrom($columns));
            if ($validator->fails()) {
                $this->failedRows->push($raw + ['_errors' => implode('; ', $validator->errors()->all())]);
                $this->skipped++;
                continue;                                 // pula ANTES do save
            }
            $importer->resolveRecord($data)->fill($data)->save();
            $this->imported++;
        }
    });
    $logger->progress($importId, $this->imported, $this->skipped);
}
if ($this->failedRows->isNotEmpty()) {
    $path = writeFailedRowsCsv($this->failedRows);       // spatie/simple-excel direto
    $logger->finished($importId, $path);
}
```

**Decisões de design:**
- **Transação por-chunk** (não global): preserva a semântica skip+failed-rows — chunk que
  persiste não é revertido por falhas de *outro* chunk. Linhas inválidas nem entram na
  transação. Memory-safe para arquivos grandes.
- **Progresso:** `ImportLogger->progress()` grava `imported/skipped/total` por `importId`
  (cache). UI pode fazer poll (broadcast via realtime fica fora do 0.17 — só o contrato pronto;
  `NullImportLogger` é o default).
- **Failed-rows CSV:** escrito com `spatie/simple-excel` diretamente (sem acoplar ao pacote
  export), servido por `FailedRowsDownloadController` com autorização.

## Autorização (segurança)

Upload e download do failed-rows CSV **exigem** autorização:
- `ImportAction extends Action` → herda o gate por-ação (`authorize('import')` / policy do Resource).
- `FailedRowsDownloadController` valida que o `userId` do import bate com o autenticado (ou policy).

Não altera auth/crypto/secrets existentes — é aplicação do gate já existente a novos entry-points.
Coberto por teste (padrão "authz-at-every-entry-point" dos loops anteriores).

## Dependência

`spatie/simple-excel` no `composer.json` do novo pacote `arqel/import`. **Já é dependência do
pacote `export`** — não é dep nova no ecossistema. Confirmada.

## Testes (ADR-008, alvo ≥90%)

**Unit (Pest, `packages/import/tests/Unit/`):**
- `ImportColumnTest` — setters + defaults.
- `CsvReaderTest` / `XlsxReaderTest` — leem fixture real, yield arrays por header, lazy (`Generator`).
- `ImportActionTest` — fluent + gate de `Action`.
- `ProcessImportJobTest` (coração):
  - válidas → persistidas (`assertDatabaseHas`);
  - inválidas → puladas, contadas em `skipped`, no failed-rows CSV com `_errors`;
  - transação por-chunk: chunk válido persiste mesmo com falhas noutro chunk;
  - `resolveRecord` custom → upsert não duplica;
  - header `requiredMapping` ausente → falha cedo, nada persistido;
  - progresso: `ImportLogger->progress()` chamado com contagens corretas (fake logger).

**Integration leve:** job e2e com CSV misto (válido/inválido) → N importados, M pulados,
failed-rows CSV parseável com mensagens corretas.

**Fixtures:** `users-valid.csv`, `users-mixed.csv` (1 email inválido + 1 duplicado), `users.xlsx`.

## Fora de escopo do 0.17 (YAGNI)

- UI de mapeamento interativa (fase 2).
- Broadcast de progresso via realtime (só o contrato `ImportLogger` fica pronto).
- Importação de relações aninhadas.
- Componente React de upload no painel: o 0.17 entrega backend + `ImportAction` + upload/download
  controllers; a página Inertia de upload pode ser follow-up mínimo — decidido no plano.
- `make:import` generator (follow-up; segue o padrão de `arqel:resource` já corrigido).

## Documentação a produzir

- `packages/import/SKILL.md` (estrutura canônica) + `README.md`.
- `apps/docs` página de guide + `reference/php/import`.
- Entrada no `CHANGELOG.md` sob `[Unreleased]`.
