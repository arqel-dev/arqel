# SKILL.md — arqel-dev/versioning

> Contexto canônico para AI agents trabalhando no pacote `arqel-dev/versioning`.

## Purpose

Time-travel para Eloquent records do Arqel (cobre RF-IN-05). O pacote
adiciona um trait `Versionable` que grava snapshots completos de cada
mudança em `arqel_versions`, com diff por campo, restore não-destrutivo,
endpoints HTTP de history/restore e retention via Artisan command + Job
queueable.

A integração com `arqel-dev/core` é **opcional** — o trait funciona standalone
e os controllers degradam para `404` quando o `ResourceRegistry` não está
bound. Sem hard-dep em `spatie/laravel-eventsourcing`.

## Status

### Entregue (VERS-001..VERS-007)

**Schema + Service Provider (VERS-001).** `VersioningServiceProvider`
auto-discovered via `extra.laravel.providers`. Migration
`2026_05_01_000000_create_arqel_versions_table` cria `arqel_versions` com
`morphs('versionable')`, JSON `payload`, JSON `changes`, `created_by_user_id`
(indexed), nullable `reason`, `created_at`. Registrada via
`hasMigration('2026_05_01_000000_create_arqel_versions_table')` — o nome
**datado** (não `create_arqel_versions_table`) tem de bater com o ficheiro
shipado, senão `vendor:publish` não resolve a publicação (#52). Config
publicável `arqel-versioning.php`
expõe `enabled`, `keep_versions`, `prune_strategy`, `audit_user`, `user_model`.

**Model + Relations (VERS-001/002).** `Models\Version` (final,
`$timestamps = false`, append-only). Casts `payload`/`changes` → `array`,
`created_at` → `datetime`. `versionable(): MorphTo` para o source model.
`user(): ?BelongsTo` defensivo — lê `arqel-versioning.user_model`
(default `App\\Models\\User`); devolve `null` quando a classe não existe
ou não é Eloquent `Model` (apps minimalistas / testes).

**Trait + Hooks (VERS-002).** `Concerns\Versionable` (consumido por
user-land Eloquent models):

- `bootVersionable()` registra três hooks: `created` grava snapshot
  inicial; `updating` captura diff dirty (filtra `created_at`/`updated_at`)
  em `self::$arqelVersioningPendingDiff[spl_object_id($model)]`;
  `updated` consome o diff e grava nova `Version`. Idempotência: diff
  vazio → early-return (saves só de timestamps, e.g. `touch()`, não
  geram version). Master switch `arqel-versioning.enabled === false`
  desliga todos os hooks.
- `versions(): MorphMany<Version>` ordenado por `created_at desc, id desc`.
- `currentVersion(): ?Version` atalho para `versions()->first()`.
- `restoreToVersion(int|Version): bool` — não-destrutivo. **Cast-aware**
  (#187): reaplica os casts ao restaurar (`setAttribute` por chave em vez
  de `forceFill`), mantendo o bypass de mass-assignment mas reserializando
  corretamente — um payload `array` volta como array, não como string
  JSON double-encodada. `save` dispara o hook, gera nova Version, permite
  "undo restore". Defensive cross-record: devolve `false` quando version
  não pertence ao record.
- `pruneOldVersions(): int` aplica retention 'count' por record;
  invocado automaticamente após cada `writeVersion`. Suporta `strategy
  != 'count'` (early-return 0) e `keep=0` (unbounded). O predicado de
  prune filtra `versionable_type` por `getMorphClass()` (não pelo FQCN
  cru), casando o valor que `associate()` persiste — logo respeita
  `Relation::enforceMorphMap()` (sob morph map o type é o alias, não a
  classe).
- `resolveAuditUserId()` privado: resolve **qualquer callable** em
  `arqel-versioning.audit_user` via `is_callable()` — string `'FQCN::method'`,
  `Closure`, ou array `[$object, 'method']` (#167); fallback `Auth::id()`;
  resultado não-int (ou ambos null) → `null`.

**History endpoint (VERS-003 — slice PHP).**
`Http\Controllers\VersionHistoryController` single-action,
`GET /admin/{resource}/{id}/versions` (rota `arqel.versioning.history`,
middleware `web,auth`). Resolve `ResourceRegistry` por FQCN-string e
devolve `404` quando ele não está bound. Valida via
`class_uses_recursive` que o model alvo usa o trait (`422` caso
contrário). Autorização `view` exigida quando existe um named gate
(`Gate::define`) **OU** uma Policy registrada para o model
(`Gate::getPolicyFor`) — `403 {message: "Forbidden"}` no deny, sem
vazar o snapshot via `?include=payload`; sem gate **e** sem policy
(scaffold-mode) o acesso é liberado. `Gate::has()` sozinho **não**
consulta Policies, por isso a checagem inclui `getPolicyFor` (#91).
Pagination: `?per_page=20` default, **clamped a `[1, 100]`**. Eager-load
`with('user')` apenas quando `Version::user()` resolve. Resposta inclui
`meta.keep_versions` e `meta.total`. Slice React (B39) consome esta
rota para popular a tab "History".

**VersionPresenter (VERS-003).** `final readonly class` que serializa
`Version` para payload JSON-friendly: `id`, `created_at` ISO 8601,
`changes_summary`, `changes`, `user`, `is_initial`, opcional `payload`.
Resumos: `null` → `"Created"`; `[]` → `"No changes"`; 1 field → singular
(`"Changed 1 field: title"`); N+ fields → plural (`"Changed 5 fields:
a, b, c, d, e"`). `payload` **não é exposto por default** — pode conter
PII / segredos; controller só inclui mediante `?include=payload`.

**Restore endpoint (VERS-005).** `Http\Controllers\VersionRestoreController`
single-action, `POST /admin/{resource}/{id}/versions/{versionId}/restore`.
Mesma resolução defensiva do registry. Valida trait (`422`); a
autorização `update` é exigida quando existe um named gate
(`Gate::define`) **OU** uma Policy registrada para o model
(`Gate::getPolicyFor`) — `403` no deny (via `AuthorizationException`);
sem gate **e** sem policy (scaffold-mode) o pedido é liberado.
`Gate::has()` sozinho **não** consulta Policies, por isso a checagem
inclui `getPolicyFor` (#91). `404` para slug/record/version desconhecida ou cross-record.
Sucesso → `200 {restored: true, new_version_id: <int>}`. Falha
inesperada → `Log::error('arqel.versioning.restore_failed', …)` +
`500 {restored: false, message: …}`.

**Retention (VERS-006).** `Console\PruneVersionsCommand`
(`arqel:versions:prune`) com flags combináveis:

- `--days=N` — apaga rows com `created_at < now() - N days`.
- `--keep=N` — mantém top-N por `(versionable_type, versionable_id)`.
- sem flags — usa `arqel-versioning.keep_versions` como `--keep` default.
- `--dry-run` — emite `[DRY RUN] would delete <N> rows.` sem deletar.
- happy path emite `Pruned <N> version rows.` (verbose).

Idempotente — rodar duas vezes é safe. `Jobs\PruneOldVersionsJob`
(`ShouldQueue` + `Dispatchable` + `SerializesModels`) é wrapper para
schedulers/queue: `__construct(?int $days, ?int $keep)`,
`Artisan::call('arqel:versions:prune', $params)` em `handle()`.
Round-trip serialize/unserialize preserva props.

**Coverage gaps + canonical SKILL (VERS-007).** Suite Pest 3 com 58
testes (44 do trait/controllers/command/presenter + 14 cobertura
adicional em `tests/Unit/Coverage/VersioningCoverageGapsTest.php`).
PHPStan level max clean. Coverage extra cobre: `audit_user` callable
resolver (int, non-int, ausência), `prune_strategy != 'count'`,
`keep_versions=0` unbounded, save sem mudanças efetivas (`touch()`),
`Version::user()` com classe não-Model, `VersionPresenter::summarize()`
(1 field, 5 fields, empty array), `per_page` clamp acima do max (100)
e abaixo do mínimo (default 20), `Pruned` verbose output, e
serialização do `PruneOldVersionsJob`.

### Por chegar

- **VERS-008** — Docs comparativo: versioning vs activity log
  (`arqel-dev/audit`).
- Time-based prune **strategy** dentro do trait (atualmente só o
  Artisan command suporta `--days`).
- Version **comparison API** (diff entre duas versions arbitrárias,
  além do diff incremental `[old, new]` por save) — lado PHP ainda não
  implementado.

## Conventions

- Trait é **opt-in** — só os models com `use Versionable` geram
  snapshots. Sem behavior global.
- Snapshots são **cast-aware** (#187): gravam o resultado de
  `$model->getAttribute($key)` (valor **com cast aplicado**) sobre o
  mesmo key-set de `getAttributes()` — payload completo. Casts
  `array`/`json`/`object`/`collection`/`encrypted` ficam desserializados,
  não como string JSON crua (senão o restore re-encodava → corrupção
  silenciosa). `changes` traz só o diff (`[old, new]` por campo), com
  **ambos os lados também cast** (antes misturava `getOriginal()` cast com
  `getDirty()` raw → diff type-assimétrico).
- Restore é **versionado** e **cast-aware** — `restoreToVersion()`
  reaplica casts via `setAttribute` por chave e faz `save()` que dispara
  o hook e cria nova Version (permite undo).
- Append-only: `Version` tem `$timestamps = false`; única write é o
  insert no hook + delete via prune. Nunca update.
- Prune por contagem: ao gravar, qualquer version além de
  `keep_versions` (mais antigas) é deletada. `0` = unbounded.
  `strategy != 'count'` é no-op (até time-based ganhar trait support).
- Audit user: `auth()->id()` por padrão; sobrescrita por **qualquer
  callable** em `arqel-versioning.audit_user` — string `'FQCN::method'`,
  `Closure` ou array `[$object, 'method']` (#167) — útil em CLI/jobs onde
  `Auth` não está hidratado.
- `payload` privado por default na API HTTP — exige `?include=payload`
  explicitamente (PII guard).
- `declare(strict_types=1)` obrigatório. `Version`, `VersionPresenter`,
  controllers, command, job são `final`. `Versionable` é trait.

## Anti-patterns

- **Versionar sem prune em produção** (`keep_versions=0` + sem
  `arqel:versions:prune` agendado) → storage cresce sem limite (GB
  por milhão de records).
- **Expor `payload` sem filtros sensíveis** — snapshots contêm tudo
  do model (todas as chaves de `getAttributes()`, com casts aplicados),
  incluindo password hashes/tokens. Front-end só deve pedir
  `?include=payload` em telas internas e/ou após filtrar campos
  sensíveis no consumer.
- **Restore destrutivo / em loop** — cada `restoreToVersion()` cria
  nova Version e dispara prune. Para migrations bulk, manipule a
  tabela diretamente em vez de iterar restores.
- **`audit_user` callable que acessa `Request`/`session()` fora de
  HTTP** — o resolver é invocado também em CLI/queue/jobs onde esses
  serviços não estão hidratados. Use `Auth::id()` ou injete via
  config/binding.
- **Aplicar `Versionable` em campos voláteis de alta frequência**
  (`last_seen_at`, contadores tickeados a cada request) — vai inflar
  a tabela. Filtre os campos antes do save ou desligue versioning
  pontual com `config(['arqel-versioning.enabled' => false])`.

## Examples

### Setup mínimo + trait

```php
use Arqel\Versioning\Concerns\Versionable;
use Illuminate\Database\Eloquent\Model;

final class Article extends Model
{
    use Versionable;

    protected $fillable = ['title', 'body', 'status'];
}

$article = Article::create(['title' => 'Hello', 'body' => '...', 'status' => 'draft']);
$article->update(['title' => 'Hello v2']);

$article->versions()->count();   // 2
$article->currentVersion();       // Version do último save
```

### History endpoint via Inertia

```http
GET /admin/articles/42/versions?per_page=20 HTTP/1.1
Accept: application/json
```

Resposta (`200 OK`):

```json
{
  "versions": {
    "data": [
      {
        "id": 87,
        "created_at": "2026-05-01T12:34:56+00:00",
        "changes_summary": "Changed 2 fields: title, body",
        "changes": {
          "title": ["Hello", "Hello v2"],
          "body":  ["...", "Updated body"]
        },
        "user": { "id": 7, "name": "Diogo" },
        "is_initial": false
      }
    ],
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  },
  "meta": { "keep_versions": 50, "total": 1 }
}
```

Para incluir o snapshot completo: `?include=payload`. Por default ele é
omitido — payloads podem conter PII e segredos do model.

### Cenários completos (VERS-008)

Ver `../../apps/docs/examples/versioning/`:

- [`index.md`](../../apps/docs/examples/versioning/index.md) — comparativo
  versioning vs `arqel-dev/audit`, decision tree e anti-patterns.
- [`cms-articles.md`](../../apps/docs/examples/versioning/cms-articles.md) —
  CMS com restore de artigos, schedule de prune e UI React.
- [`ecommerce-orders.md`](../../apps/docs/examples/versioning/ecommerce-orders.md) —
  por que **NÃO** versionar pedidos, com aritmética de storage.
- [`legal-contracts.md`](../../apps/docs/examples/versioning/legal-contracts.md) —
  versioning + audit combinados para compliance legal-tech.

### Restore via UI

```php
$article = Article::find($id);
$target = $article->versions()->find($versionId);

if ($article->restoreToVersion($target)) {
    session()->flash('success', 'Restored to ' . $target->created_at);
}
```

Ou diretamente via HTTP autenticado:

```php
Http::asJson()->post(route('arqel.versioning.restore', [
    'resource'  => 'articles',
    'id'        => 42,
    'versionId' => 7,
]));
// → {"restored": true, "new_version_id": 88}
```

### Pruning automático scheduler

```php
// config/arqel-versioning.php
'keep_versions' => 20,
'prune_strategy' => 'count',
```

Poda corre automaticamente após cada novo snapshot. Para retention
cross-record (`--days`) ou agendado, use o Artisan command + Job:

```php
// app/Console/Kernel.php
$schedule->command('arqel:versions:prune --days=90')->weekly();

// ou via job (queue)
$schedule->job(new PruneOldVersionsJob(days: 90))->weekly();

// Manual / dry-run
php artisan arqel:versions:prune --keep=20 --dry-run
```

Forçar manual num record único:

```php
$article->pruneOldVersions();   // devolve número de rows deletadas
```

### Custom audit_user resolver

Útil em CLI, queues ou apps multi-tenant onde `Auth::id()` não reflete
o autor real:

```php
// config/arqel-versioning.php
'audit_user' => \App\Versioning\AuditUser::class . '::resolve',

// Closure também é aceita (qualquer callable — #167):
'audit_user' => fn (): ?int => app('current.actor.id'),
```

```php
namespace App\Versioning;

final class AuditUser
{
    public static function resolve(): ?int
    {
        // Ex.: contexto injetado por job middleware
        return app('current.actor.id');
    }
}
```

`null` ou retorno não-int → grava `created_by_user_id = null`.

## Related

- Source: [`packages/versioning/src/`](./src/)
- Testes: [`packages/versioning/tests/`](./tests/)
- Tickets: [`PLANNING/10-fase-3-avancadas.md`](../../PLANNING/10-fase-3-avancadas.md) §VERS-001..VERS-007
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Versioning
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3 + tests-first
- Pacotes vizinhos:
  - `arqel-dev/audit` — audit log complementar (eventos vs snapshots).
  - `arqel-dev/workflow` — layout de scaffold idêntico (state machines).
