# `arqel-dev/versioning` — Referência de API

Namespace `Arqel\Versioning\`. Viagem no tempo para registros Eloquent: uma trait `Versionable` que grava um snapshot completo de cada mudança em `arqel_versions`, um diff por field, restore não destrutivo, endpoints HTTP de histórico e restore, e retenção através de um comando Artisan mais um job enfileirável.

A integração com `arqel-dev/core` é **opcional** — a trait funciona de forma autônoma e os controllers degradam para `404` quando o `ResourceRegistry` não está bound. Não há dependência rígida de `spatie/laravel-eventsourcing`.

## `Arqel\Versioning\Concerns\Versionable` (trait)

Opt-in por model. Somente models que declaram `use Versionable` produzem snapshots — não há comportamento global.

| Método | Tipo | Descrição |
|---|---|---|
| `bootVersionable()` | `static void` | Registra os hooks `created` / `updating` / `updated` |
| `versions()` | `MorphMany<Version>` | Ordenado por `created_at desc, id desc` |
| `currentVersion()` | `?Version` | Atalho para `versions()->first()` |
| `restoreToVersion(int\|Version $version)` | `bool` | Restore não destrutivo |
| `pruneOldVersions()` | `int` | Aplica a retenção por contagem para este registro; retorna as linhas removidas |

`created` grava o snapshot inicial; `updating` captura o diff dos atributos sujos (filtrando `created_at` / `updated_at`); `updated` consome esse diff e grava uma nova `Version`. Um diff vazio retorna cedo, de modo que um `touch()` nunca produz uma versão. A chave-mestra `arqel-versioning.enabled === false` desabilita todos os hooks.

Os snapshots são **cast-aware**: armazenam `$model->getAttribute($key)` (o valor com os casts aplicados) sobre o mesmo conjunto de chaves de `getAttributes()`. Casts `array`/`json`/`object`/`collection`/`encrypted` são armazenados desserializados, de modo que um restore não os recodifica. `changes` carrega apenas o diff (`[old, new]` por field), com ambos os lados também com casts aplicados.

`restoreToVersion()` reaplica os casts chamando `setAttribute()` por chave, em vez de `forceFill()` — ainda contorna o mass assignment, mas reserializa corretamente. O `save()` subsequente dispara o hook e cria uma nova `Version`, o que torna possível "desfazer o restore". Retorna `false` defensivamente quando a versão não pertence ao registro.

`pruneOldVersions()` roda automaticamente após cada escrita. Retorna `0` cedo quando `prune_strategy != 'count'`; `keep_versions = 0` significa ilimitado. O predicado de prune filtra `versionable_type` por `getMorphClass()`, correspondendo ao que `associate()` persiste e, portanto, respeitando `Relation::enforceMorphMap()`.

O id do usuário de auditoria é resolvido através de qualquer callable em `arqel-versioning.audit_user` — uma string `'FQCN::method'`, uma `Closure`, ou um array `[$object, 'method']` — caindo em `Auth::id()` como fallback. Um resultado que não seja int (ou ambos sendo null) armazena `null`.

## `Arqel\Versioning\Models\Version` (final)

Linha append-only em `arqel_versions`. `$timestamps = false`; `payload` e `changes` com cast para `array`, `created_at` para `datetime`.

| Método | Tipo | Descrição |
|---|---|---|
| `versionable()` | `MorphTo` | O model de origem |
| `user()` | `?BelongsTo` | Defensivo — lê `arqel-versioning.user_model` (default `App\Models\User`) e retorna `null` quando a classe não existe ou não é um `Model` do Eloquent |

A migration `2026_05_01_000000_create_arqel_versions_table` cria `morphs('versionable')`, `payload` JSON, `changes` JSON, `created_by_user_id` indexado, `reason` nullable e `created_at`.

## `Arqel\Versioning\VersionPresenter` (final readonly)

`VersionPresenter::toArray(Version $version, bool $includePayload = false): array` serializa uma versão em um payload amigável a JSON:

```
{
    id: int,
    created_at: string|null,          // ISO 8601
    changes_summary: string,
    changes: array<string, [mixed, mixed]>|null,
    user: {id: int, name: string|null}|null,
    is_initial: bool,
    payload?: array<string, mixed>,   // apenas quando $includePayload
}
```

Resumos: `changes === null` → `"Created"`; `[]` → `"No changes"`; um field → singular (`"Changed 1 field: title"`); N fields → plural (`"Changed 5 fields: a, b, c, d, e"`).

`payload` **não** é exposto por padrão — snapshots podem conter PII e segredos, então o controller só o inclui mediante um `?include=payload` explícito.

## HTTP

Ambas as rotas são registradas com os middlewares `web` + `auth`.

| Verbo | Route | Nome | Controller |
|---|---|---|---|
| GET | `/admin/{resource}/{id}/versions` | `arqel.versioning.history` | `Http\Controllers\VersionHistoryController` |
| POST | `/admin/{resource}/{id}/versions/{versionId}/restore` | `arqel.versioning.restore` | `Http\Controllers\VersionRestoreController` |

Ambos são controllers `final` de ação única, que resolvem o `ResourceRegistry` pela string FQCN e retornam `404` quando ele não está bound, e validam via `class_uses_recursive` que o model alvo usa a trait (`422` caso contrário).

A autorização é ciente de Policies: a ability (`view` para o histórico, `update` para o restore) é aplicada quando existe um Gate nomeado (`Gate::define`) **ou** uma Policy registrada para o model (`Gate::getPolicyFor`) — `Gate::has()` sozinho nunca consulta Policies. Em caso de negação, o histórico retorna `403 {message: "Forbidden"}` sem vazar o snapshot via `?include=payload`, e o restore retorna `403` através de uma `AuthorizationException`. Sem gate nem policy (modo scaffold), o acesso é permitido.

`VersionHistoryController` pagina com `?per_page=` (default 20, limitado a `[1, 100]`), faz eager loading de `with('user')` apenas quando `Version::user()` resolve, e inclui `meta.keep_versions` mais `meta.total` na resposta.

`VersionRestoreController` retorna `404` para slug, registro ou versão desconhecidos, e para uma versão que pertence a outro registro. O sucesso é `200 {restored: true, new_version_id: <int>}`; uma falha inesperada loga `arqel.versioning.restore_failed` e retorna `500 {restored: false, message: …}`.

## Retenção

### `Arqel\Versioning\Console\PruneVersionsCommand` (final)

`arqel:versions:prune`, com flags combináveis:

| Flag | Efeito |
|---|---|
| `--days=N` | Remove linhas com `created_at < now() - N days` |
| `--keep=N` | Mantém as N primeiras linhas por `(versionable_type, versionable_id)` |
| *(sem flags)* | Usa `arqel-versioning.keep_versions` como default de `--keep` |
| `--dry-run` | Emite `[DRY RUN] would delete <N> rows.` sem remover nada |

O caminho feliz emite `Pruned <N> version rows.` (verbose). O comando é idempotente — rodá-lo duas vezes é seguro.

### `Arqel\Versioning\Jobs\PruneOldVersionsJob` (final)

Wrapper `ShouldQueue` + `Dispatchable` + `SerializesModels` para schedulers e filas. Construtor `(?int $days, ?int $keep)`; `handle()` chama `Artisan::call('arqel:versions:prune', …)`. Os ciclos de serialize/unserialize preservam as propriedades.

## Configuração

`config/arqel-versioning.php` expõe `enabled`, `keep_versions`, `prune_strategy`, `audit_user` e `user_model`.

## Exemplo

```php
use Arqel\Versioning\Concerns\Versionable;

final class Article extends Model
{
    use Versionable;

    protected $fillable = ['title', 'body', 'status'];
}

$article = Article::create(['title' => 'Hello', 'body' => '...', 'status' => 'draft']);
$article->update(['title' => 'Hello v2']);

$article->versions()->count();  // 2
$article->currentVersion();     // Version do último save
```

```php
$article = Article::find($id);
$target = $article->versions()->find($versionId);

if ($article->restoreToVersion($target)) {
    session()->flash('success', 'Restored to '.$target->created_at);
}
```

```php
// config/arqel-versioning.php
'keep_versions' => 20,
'prune_strategy' => 'count',
'audit_user' => fn (): ?int => app('current.actor.id'),
```

```php
// routes/console.php
Schedule::command('arqel:versions:prune --days=90')->weekly();
Schedule::job(new PruneOldVersionsJob(days: 90, keep: null))->weekly();
```

## Relacionados

- SKILL: [`packages/versioning/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/versioning/SKILL.md)
- Código-fonte: [`packages/versioning/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/versioning/src/)
