# SKILL.md — arqel-dev/import

> Contexto canônico para AI agents.

## Purpose

`arqel-dev/import` entrega a pipeline de importação do Arqel — lê arquivos CSV/XLSX enviados pelo usuário, valida linha-a-linha contra colunas declarativas, persiste os registros válidos em chunks transacionais e devolve um CSV de linhas falhadas para download. Fecha a lacuna competitiva vs Filament/Nova (o `arqel-dev/export` já existia; agora o caminho inverso também é first-class).

## Key Contracts

- **`Arqel\Import\ImportFormat`** — enum `string` (`CSV`/`XLSX`) com `extension(): string` e `fromExtension(string): self` (lança `InvalidArgumentException` para extensões não suportadas).
- **`Arqel\Import\Contracts\FileReader`** — `read(string $source): iterable<int, array<string, string|null>>`. Implementações (`CsvReader`, `XlsxReader`, ambas backed por `spatie/simple-excel`) fazem streaming linha-a-linha, nunca carregam o arquivo inteiro em memória.
- **`Arqel\Import\ImportColumn`** — descritor declarativo de coluna. `make('email')` casa com o header `email` do arquivo. Fluent: `label()`, `rules(array)` (regras de validação Laravel por linha), `fillUsing(Closure)` (transforma o valor cru antes da validação), `requiredMapping(bool = true)` (header ausente é erro de setup, não erro por-linha).
- **`Arqel\Import\Importer`** (`abstract`) — classe base que o app consumidor estende. Declara `public static string $model` e `columns(): array<ImportColumn>`. `rules()` deriva as regras de validação a partir de `columns()`. `resolveRecord(array $data): Model` tem default de insert (`new $model`) — override para upsert (`Model::firstOrNew(...)`).
- **`Arqel\Import\Contracts\ImportLogger`** — hook de lifecycle/progresso (`logQueued`, `progress`, `logCompleted`, `logFailed`). Default binding `NullImportLogger` (no-op) via `singletonIf` — apps sobrescrevem para persistir uma tabela `imports` e/ou notificar o usuário.
- **`Arqel\Import\Jobs\ProcessImportJob`** — `final class implements ShouldQueue`. Construtor com props readonly: `string $importId`, `ImportFormat $format`, `class-string<Importer> $importerClass`, `string $sourcePath`, `?string $failedRowsDir`. `handle(ImportLogger $logger)` faz streaming do `FileReader`, processa em chunks de 100 linhas dentro de `DB::transaction()` por chunk, valida cada linha via `Validator::make($data, $rules)`, e acumula linhas falhadas com uma coluna sintética `_errors`. Ao final, escreve o CSV de falhas (se houver) e chama `logCompleted`/`logFailed`.
- **`Arqel\Import\Actions\ImportAction`** — action tipo `toolbar` que estende `Arqel\Actions\Action` (herda a autorização por-ação em todo entry point). Factory `make(string $name)` seta label `arqel-import::import.action` + ícone `upload`. Fluent `importer(class-string<Importer>)` + `format(ImportFormat)` (default `CSV`).
- **Controllers HTTP** (`ImportUploadController`, `FailedRowsDownloadController`) — registrados via `routes/admin.php` sob `web + auth`. Sem authorization própria além disso — apps consumidoras devem envolver com sua própria gate (mesma postura do `arqel-dev/export`).

## Conventions

- `declare(strict_types=1)` obrigatório em todos os arquivos.
- Classes `final` por default, exceto `Importer` (`abstract`, é o ponto de extensão do pacote por design).
- `spatie/simple-excel` é dependência `require` (não `suggest`) — os dois readers (CSV/XLSX) dependem dele.
- **Nota ext-zip:** o reader XLSX depende de `ext-zip` (via OpenSpout, usado internamente pelo `spatie/simple-excel`). Ambientes sem `ext-zip` só conseguem importar CSV; instale a extensão para XLSX ou rode `composer install --ignore-platform-req=ext-zip` em CI/dev sem a extensão.
- Todo processamento pesado (parse + validação + persistência) roda **assíncrono** via `ProcessImportJob` — nunca sync na request HTTP, mesmo para arquivos pequenos. Isso evita timeout e mantém o padrão consistente com `arqel-dev/export`.
- Transação **por chunk** (100 linhas), não uma transação única para o arquivo inteiro — evita locks longos e permite progresso incremental via `ImportLogger::progress()`.
- Linhas falhadas nunca abortam o job inteiro — são coletadas e exportadas para um CSV de download; o import continua até o fim do arquivo.
- Traduções namespaced `arqel-import::import.*` (derivado de `name('arqel-import')` no `ImportServiceProvider`, mesmo padrão do `arqel-audit::messages.*`). Chaves: `action`, `queued`.

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

## Anti-patterns

- ❌ **Carregar o arquivo inteiro em memória** — `FileReader::read()` é sempre `iterable`/generator. Nunca `iterator_to_array()` sem necessidade antes de processar.
- ❌ **Processar o import sync na request HTTP** — sempre via `ProcessImportJob::dispatch()`. Uploads grandes travam workers de fila web-síncronos.
- ❌ **Ignorar `requiredMapping()`** para colunas obrigatórias no header — sem isso, um header ausente vira silenciosamente `null` em toda linha em vez de falhar cedo com um erro de setup claro.
- ❌ **Reimplementar upsert manualmente no chamador** — override `resolveRecord()` no `Importer`, que já participa da transação por chunk.
- ❌ **Assumir `ext-zip` disponível** sem checar — XLSX falha silenciosamente/lança exceção de dependência ausente; CSV é o fallback seguro.

## Related

- Plano/spec: `docs/superpowers/plans/2026-07-04-import-feature.md` (branch `feat/import-package`)
- Source: [`packages/import/src/`](./src/)
- Tests: [`packages/import/tests/`](./tests/)
- Pacote irmão: [`arqel-dev/export`](../export/SKILL.md) (mesma postura de streaming + auth-at-the-edge)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only (upload/download são out-of-band, fora do Inertia visit)
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
