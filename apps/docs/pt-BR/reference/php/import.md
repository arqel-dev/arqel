# `arqel-dev/import` — Referência de API

Namespace `Arqel\Import\`. Pipeline de importação CSV/XLSX: columns declarativas, processamento em chunks e transacional, download em CSV das linhas que falharam.

## `Arqel\Import\Importer` (abstract)

Class base que uma aplicação consumidora estende para declarar uma importação.

| Método | Tipo | Descrição |
|---|---|---|
| `$model` | `static class-string<Model>` | Model Eloquent de destino |
| `columns()` | `array<ImportColumn>` (abstract) | Descritores de column — declare-o |
| `resolveRecord(array $data)` | `Model` | Resolve o model ao qual uma linha validada corresponde. Default: `new $model` (insert). Sobrescreva para upsert, por exemplo `User::firstOrNew(['email' => $data['email']])` |
| `rules()` | `array<string, array>` | Regras de validação indexadas pelo nome da column, derivadas de `columns()` |

## `Arqel\Import\ImportColumn` (final)

Descritor declarativo de column. Factory: `ImportColumn::make($name)` — `$name` corresponde ao cabeçalho do arquivo.

| Método | Tipo | Descrição |
|---|---|---|
| `label(string)` | `self` | Label de exibição (default = nome) |
| `rules(array)` | `self` | Regras de validação do Laravel por linha |
| `fillUsing(Closure)` | `self` | Transforma o valor bruto da célula antes da validação |
| `requiredMapping(bool = true)` | `self` | Marca o cabeçalho como obrigatório — um cabeçalho ausente aborta o job com um erro de configuração, em vez de silenciosamente deixar todas as linhas nulas |
| `getName()` / `getLabel()` / `getRules()` / `isMappingRequired()` | getters | |
| `applyFill(?string $raw)` | `mixed` | Roda o `fillUsing` configurado (ou devolve `$raw` sem alteração) |

## `Arqel\Import\ImportFormat` (enum, backed por string)

Casos: `CSV`, `XLSX`.

| Método | Tipo | Descrição |
|---|---|---|
| `extension()` | `string` | Valor do enum (`'csv'`, `'xlsx'`) |
| `fromExtension(string)` | `self` (static) | Lança `InvalidArgumentException` para extensões não suportadas |

## `Arqel\Import\Contracts\FileReader` (interface)

`read(string $source): iterable<int, array<string, string|null>>` — faz streaming das linhas de forma lazy, indexadas pelo cabeçalho, nunca carregando o arquivo inteiro na memória. Implementações: `Readers\CsvReader`, `Readers\XlsxReader` (ambas baseadas em `spatie/simple-excel`; o XLSX exige adicionalmente a `ext-zip`).

## `Arqel\Import\Contracts\ImportLogger` (interface)

Hook de ciclo de vida/progresso.

| Método | Descrição |
|---|---|
| `logQueued(string $importId, ImportFormat $format)` | Job despachado |
| `progress(string $importId, int $imported, int $skipped)` | Chamado após cada chunk |
| `logCompleted(string $importId, int $imported, int $skipped, ?string $failedRowsPath)` | Job finalizado |
| `logFailed(string $importId, ImportFormat $format, Throwable $exception)` | Job lançou exceção |

Binding default: `Arqel\Import\Logging\NullImportLogger` (no-op), registrado via `singletonIf`. As aplicações sobrescrevem para persistir uma tabela `imports` e/ou notificar usuários.

## `Arqel\Import\Jobs\ProcessImportJob` (final, `implements ShouldQueue`)

Faz streaming do arquivo de origem em chunks de 100 linhas, cada um dentro da sua própria `DB::transaction()`. Valida cada linha via `Validator::make($data, $rules)`; as linhas que falham são coletadas (com uma column sintética `_errors`) em vez de abortar o job, e escritas em um CSV baixável ao final.

| Parâmetro do construtor | Tipo | Descrição |
|---|---|---|
| `$importId` | `string` | Correlaciona as chamadas de progresso/log |
| `$format` | `ImportFormat` | |
| `$importerClass` | `class-string<Importer>` | |
| `$sourcePath` | `string` | Caminho absoluto do arquivo enviado |
| `$failedRowsDir` | `?string` | Default `storage_path('app/arqel-imports')` quando `null` |

`handle(ImportLogger $logger): void` é o ponto de entrada (o Laravel resolve `$logger` pelo container). As células do CSV de linhas com falha são sanitizadas contra CSV formula injection (um `= + - @` inicial ou caractere de controle recebe um apóstrofo como prefixo).

## `Arqel\Import\Actions\ImportAction` (final, `extends Arqel\Actions\Action`)

Action de toolbar que abre o fluxo de upload de importação para um Resource. Estender a `Action` do framework significa herdar a autorização por action em todos os pontos de entrada.

| Método | Tipo | Descrição |
|---|---|---|
| `ImportAction::make(string $name)` | `static` | Factory. Define o label `arqel-import::import.action` + o ícone `upload` |
| `importer(class-string<Importer>)` | `self` | |
| `format(ImportFormat)` | `self` | Default `ImportFormat::CSV` |
| `getImporterClass()` / `getFormat()` | getters | |

## HTTP

Registrado em `routes/admin.php` sob `web` + `auth` (sem autorização embutida além disso — as aplicações envolvem com o próprio gate):

| Verbo | Route | Nome | Controller |
|---|---|---|---|
| POST | `admin/imports` | `arqel.imports.upload` | `Http\Controllers\ImportUploadController` |
| GET | `admin/imports/{importId}/failed-rows` | `arqel.imports.failed-rows` | `Http\Controllers\FailedRowsDownloadController` |

## Exemplo

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

## Relacionados

- SKILL: [`packages/import/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/import/SKILL.md)
- Código-fonte: [`packages/import/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/import/src/)
- Pacote irmão: [`arqel-dev/export`](/pt-BR/reference/php/export) (mesma postura de streaming + autorização na borda)
