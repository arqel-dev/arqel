# SKILL.md — arqel-dev/import

> Contexto canônico para AI agents.

## Purpose

`arqel-dev/import` entrega a pipeline de importação de primeira classe do Arqel — lê um arquivo CSV/XLSX enviado pelo usuário, valida cada linha contra regras declarativas, persiste os registros válidos em chunks (com transação por-chunk) e produz um CSV de linhas falhadas para download. Fecha a lacuna competitiva vs Filament/Nova (que já tinham import de fábrica; o Arqel já tinha `arqel-dev/export`, mas não o caminho inverso). Cobre o par simétrico de RF-T-14 (export) do lado de entrada de dados.

## Key Contracts

- **`Arqel\Import\Importer`** (`abstract class`) — classe que a app consumidora estende para declarar uma importação. Define `public static string $model` (a model Eloquent alvo) e implementa `columns(): array<int, ImportColumn>`. Opcionalmente sobrescreve `resolveRecord(array $data): Model` para fazer upsert em vez de insert (default: `new $model`). `rules(): array<string, array>` deriva as regras de validação Laravel de `columns()` automaticamente — não precisa redeclarar.
- **`Arqel\Import\ImportColumn`** — descritor declarativo de coluna, construído via `ImportColumn::make('email')`. Fluent: `label(string)`, `rules(array)`, `fillUsing(Closure)` (transforma o valor cru antes da validação), `requiredMapping(bool = true)` (marca o header como obrigatório — header ausente é erro de setup, não erro por-linha).
- **`Arqel\Import\Actions\ImportAction`** — action de toolbar (`type = 'toolbar'`) que abre o fluxo de upload de um Resource. `ImportAction::make('import')->importer(UserImporter::class)->format(ImportFormat::CSV)`. Estende `Arqel\Actions\Action`, herdando o gate de autorização por-ação (`authorize()`/`canBeExecutedBy()`).
- **`Arqel\Import\ImportFormat`** — enum `string` (`CSV`/`XLSX`) com `extension(): string` e `fromExtension(string): self` (lança `InvalidArgumentException` para extensão não suportada).
- **`Arqel\Import\Contracts\FileReader`** — contrato format-agnostic (`read(string $source): iterable<array<string, string|null>>`), streaming linha-a-linha. `CsvReader` e `XlsxReader` (ambas backed por `spatie/simple-excel`) implementam.
- **`Arqel\Import\Jobs\ProcessImportJob`** (`final class implements ShouldQueue`) — orquestra o fluxo completo: lê o arquivo em chunks de 100 linhas, valida cada linha contra `Importer::rules()`, persiste o chunk inteiro numa transação DB (`DB::transaction`), acumula linhas falhadas com a mensagem de erro (`_errors`), e ao final escreve um CSV de falhas (se houver) via `ImportLogger::logCompleted()`. Qualquer `Throwable` durante o processo aciona `ImportLogger::logFailed()` e relança.
- **`Arqel\Import\Contracts\ImportLogger`** — hook de ciclo-de-vida (`logQueued`, `progress`, `logCompleted`, `logFailed`). Default binding é `NullImportLogger` (no-op) via `singletonIf` — apps sobrescrevem para persistir uma tabela `imports` e/ou notificar o usuário.
- **Controllers** — `ImportUploadController` (`POST admin/imports`, valida upload, dispara `ProcessImportJob`) e `FailedRowsDownloadController` (`GET admin/imports/{importId}/failed-rows`, serve o CSV de falhas). Ambos sob middleware `web + auth` e consultam o Gate `import` quando registrado (scaffold mode: sem Gate/Policy, permite — mesmo padrão de `Arqel\Fields\Http\Controllers\FieldUploadController`).

## Conventions

- `declare(strict_types=1)` obrigatório em todos os arquivos.
- **Fluxo failed-rows por-linha, não fail-fast**: uma linha inválida não aborta o import inteiro — é coletada em `_errors` e o resto do chunk continua. Só um header obrigatório ausente (`requiredMapping(true)`) é tratado como erro de setup (`InvalidArgumentException`, aborta o job inteiro).
- **Transação por-chunk, não por-arquivo inteiro**: `DB::transaction()` envolve cada chunk de 100 linhas, não o import inteiro — um arquivo de 10k linhas não segura uma única transação gigante. Trade-off: se o job falhar no meio, chunks já commitados ficam persistidos (não é tudo-ou-nada no nível do arquivo).
- **`ProcessImportJob` guarda apenas FQCN + paths, nunca a coleção**: o construtor recebe `importId`, `format`, `importerClass` (class-string), `sourcePath`, `failedRowsDir` — nunca dados serializados da fila, mantendo o payload da queue pequeno (mesmo trade-off do `RecordsResolver` em `arqel-dev/export`).
- **Namespace de tradução é `arqel-import::`** (não `arqel::`) — segue o padrão dos pacotes satélite (`arqel-export::`, `arqel-audit::`, etc.), diferente de `arqel-dev/core` que usa o namespace raiz `arqel::`. Chaves atuais: `arqel-import::import.action`, `arqel-import::import.queued`.
- **Nota ext-zip**: `XlsxReader` depende de `spatie/simple-excel` no modo `xlsx`, que por sua vez requer a extensão PHP `ext-zip` (via `ext-zip` do OpenSpout). Sem `ext-zip` instalado, os testes de XLSX são pulados (`markTestSkipped`) — CSV funciona sempre, XLSX é condicional ao ambiente.

## Examples

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
                ->rules(['required', 'email'])
                ->requiredMapping(),
            ImportColumn::make('role')
                ->fillUsing(fn (?string $raw): string => $raw ?? 'member'),
        ];
    }

    // Upsert em vez de insert: casa por email, atualiza se já existir.
    public function resolveRecord(array $data): User
    {
        return User::firstOrNew(['email' => $data['email']]);
    }
}
```

```php
use Arqel\Import\Actions\ImportAction;
use Arqel\Import\ImportFormat;

// Dentro de Resource::actions()
ImportAction::make('import')
    ->importer(UserImporter::class)
    ->format(ImportFormat::CSV);
```

## Anti-patterns

- ❌ **Carregar o arquivo inteiro em memória** — `FileReader::read()` é sempre um generator/`iterable`; nunca materialize com `iterator_to_array()` antes de processar em `ProcessImportJob`.
- ❌ **Ignorar `requiredMapping()`** em colunas que a lógica de negócio não pode tolerar ausentes (ex.: `email` para upsert) — sem essa flag, um header ausente vira erro por-linha silencioso (`_errors` cheio) em vez de abortar cedo com uma mensagem clara.
- ❌ **Assumir que o Gate `import` está sempre registrado** — em scaffold mode (sem Gate/Policy), upload e download de failed-rows são permitidos para qualquer usuário autenticado. Apps que precisam de isolamento por-tenant/por-usuário devem registrar `Gate::define('import', ...)` e, se necessário, encadear ownership via `ImportLogger` customizado (ver docblock de `FailedRowsDownloadController`).
- ❌ **Estender `BulkAction`** para uma action de import custom — mesma restrição do `arqel-dev/export`: `Arqel\Actions\Types\BulkAction` é `final`. `ImportAction` estende `Arqel\Actions\Action` diretamente.

## Related

- Tickets: [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md)
- Plano de implementação: [`docs/superpowers/plans/2026-07-04-import-feature.md`](../../docs/superpowers/plans/2026-07-04-import-feature.md)
- Source: [`packages/import/src/`](./src/)
- Tests: [`packages/import/tests/`](./tests/)
- Pacote irmão: [`packages/export/SKILL.md`](../export/SKILL.md) (caminho simétrico de saída de dados)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only (upload/download são out-of-band, fora do Inertia visit)
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
  - [ADR-017](../../PLANNING/03-adrs.md) — Policies/Gates como mecanismo canônico de autorização app-level
