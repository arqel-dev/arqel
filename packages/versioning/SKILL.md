# SKILL.md — arqel/versioning

## Purpose

Time-travel para Eloquent records do Arqel (cobre RF-IN-05). O pacote
adiciona um trait `Versionable` que grava snapshots completos de cada
mudança em `arqel_versions`, com diff por campo e suporte a restore
não-destrutivo. UI / diff viewer / restore action ficam para tickets
posteriores.

## Status

### Entregue (VERS-001 + VERS-002 + VERS-005 + VERS-006)

- Esqueleto completo do pacote `arqel/versioning` com auto-discovery
  do `VersioningServiceProvider`.
- Migration `arqel_versions` (morphs `versionable`, JSON `payload`,
  JSON `changes`, `created_by_user_id`, `reason`, `created_at`).
- `Models\Version` final, append-only (`$timestamps = false`), com
  `versionable(): MorphTo` e `user(): ?BelongsTo` defensivo.
- `Concerns\Versionable` trait com:
  - hook `static::saved(...)` que grava snapshots em insert/update
    com idempotência (`wasChanged()` + flag `enabled`).
  - `versions(): MorphMany`, `currentVersion(): ?Version`.
  - `restoreToVersion(int|Version $version): bool` não-destrutivo —
    cria nova Version (permite "undo restore").
  - `pruneOldVersions(): int` com estratégia 'count'.
- Config publicável `arqel-versioning.php` com `enabled`,
  `keep_versions`, `prune_strategy`, `audit_user`, `user_model`.
- Suite Pest + PHPStan level max + Pint clean.

### Entregue (VERS-003 — slice PHP)

- `Http\Controllers\VersionHistoryController` single-action que serve
  `GET /admin/{resource}/{id}/versions` (rota nomeada
  `arqel.versioning.history`, middleware `web,auth`).
- `VersionPresenter` final readonly que serializa cada `Version` para
  payload JSON-friendly (`id`, `created_at` ISO 8601, `changes_summary`,
  `changes`, `user`, `is_initial`, e `payload` apenas mediante
  `?include=payload`).
- Resolução defensiva do `ResourceRegistry` por FQCN-string —
  devolve 404 quando o `arqel/core` não está bound em runtime.
- Validação por `class_uses_recursive` que o model alvo usa o trait
  `Versionable` (devolve 422 caso contrário).
- Eager-load condicional do user: `Version::user()` é `?BelongsTo` por
  design e o controller só anexa `with('user')` quando a relação é
  resolvível.
- Pagination: `?per_page=20` (default), max `100`. Resposta inclui
  `meta.keep_versions` e `meta.total`.
- Suite Pest cobre 8 cenários (200 happy path, 404 sem registry/slug/record,
  422 sem trait, paginação, include=payload, meta).

### Por chegar

- **VERS-003** — Slice React (B39): tab `History` no Resource Detail
  page consumindo este endpoint via Inertia.
- **VERS-004** — Diff viewer component (React).
- **VERS-007** — Testes E2E + cobertura ≥85%.
- **VERS-008** — Docs comparativo: versioning vs activity log.

## Restore endpoint (VERS-005)

Endpoint single-action `POST /admin/{resource}/{id}/versions/{versionId}/restore`
exposto pelo `VersionRestoreController`. Resolve `ResourceRegistry` por
slug, verifica que o model usa `Versionable`, autoriza via `Gate::authorize('update', $record)`
quando definida e invoca `restoreToVersion()`. Retorna JSON
`{"restored": bool, "new_version_id": int|null}`.

Códigos HTTP:

- `200` — restore bem-sucedido (o JSON traz o id da nova Version criada).
- `403` — Gate `update` registrada e nega o usuário corrente.
- `404` — slug desconhecido, record ausente ou version não pertence ao record.
- `422` — model alvo não usa o trait `Versionable`.
- `500` — falha não esperada (logada via `Log::error`).

```php
// Restore via cliente HTTP autenticado
Http::asJson()->post(route('arqel.versioning.restore', [
    'resource'  => 'articles',
    'id'        => 42,
    'versionId' => 7,
]));
```

## Retention policy (VERS-006)

Comando Artisan `arqel:versions:prune` apoia retention além do `keep`
automático do trait. Suporta dois critérios combináveis:

- `--days=N` — apaga versions com `created_at < now() - N days`.
- `--keep=N` — mantém top-N mais recentes por `(versionable_type, versionable_id)`.
- sem flags — usa `arqel-versioning.keep_versions` como `--keep` default.
- `--dry-run` — mostra a contagem sem apagar.

O comando é idempotente (rodar duas vezes é seguro). Para queue/scheduler
existe `Jobs\PruneOldVersionsJob` que apenas invoca o comando.

```php
// app/Console/Kernel.php
$schedule->command('arqel:versions:prune --days=90')->weekly();

// ou via job (queue)
$schedule->job(new PruneOldVersionsJob(days: 90))->weekly();
```

## Conventions

- Trait é **opt-in** — só os models que usam `Versionable` geram
  snapshots. Não há behavior global.
- Snapshots gravam o resultado de `$model->getAttributes()` (payload
  completo), e `changes` traz apenas o diff (`[old, new]` por campo).
- Restore é **versionado** — chamar `restoreToVersion()` faz `save()`,
  o que dispara o hook e cria nova Version. Defensivo cross-record:
  devolve `false` se a version não pertence ao record.
- Prune por contagem: ao gravar, qualquer version além de
  `keep_versions` (mais antigas) é deletada. `0` = unbounded.
- Audit user: `auth()->id()` por padrão; pode ser sobrescrito por um
  callable em `arqel-versioning.audit_user` (útil em CLI/jobs).
- Sem hard-dep em `spatie/laravel-eventsourcing` — pacote standalone.

## Anti-patterns

- **Não use `Versionable` para auditoria de leitura** — só capta
  writes. Para audit log de acessos use `arqel/audit`.
- **Não use para campos voláteis de alta frequência** (ex.: `last_seen_at`
  atualizado a cada request) — vai inflar a tabela. Considere ignorar
  esses campos antes do save (`->timestamps = false` no model alvo ou
  excluir explicitamente do update).
- **Não chame `restoreToVersion()` num loop** — cada chamada cria nova
  Version e dispara prune; prefira manipular a tabela diretamente para
  migrations bulk.
- **Não desligue `keep_versions=0` em produção** sem cleanup job — o
  storage cresce sem limite (GB por milhão de records).

## History endpoint (VERS-003)

A rota `arqel.versioning.history` expõe o histórico paginado de um
record. Exemplo de chamada:

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

Para incluir o `payload` completo do snapshot, passe
`?include=payload`. Por default ele é omitido — payloads podem conter
PII e segredos do model. O componente React (B39) consome esta rota
para popular a tab "History".

## Examples

Ver cenários completos em `../../docs/examples/versioning/`:

- [`README.md`](../../docs/examples/versioning/README.md) — comparativo
  versioning vs `arqel/audit`, decision tree e anti-patterns.
- [`cms-articles.md`](../../docs/examples/versioning/cms-articles.md) —
  CMS com restore de artigos, schedule de prune e UI React.
- [`ecommerce-orders.md`](../../docs/examples/versioning/ecommerce-orders.md) —
  por que **NÃO** versionar pedidos, com aritmética de storage.
- [`legal-contracts.md`](../../docs/examples/versioning/legal-contracts.md) —
  versioning + audit combinados para compliance legal-tech.

### Setup mínimo

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

### Restore via UI (preview do que VERS-005 vai expor)

```php
$article = Article::find($id);
$target = $article->versions()->find($versionId);

if ($article->restoreToVersion($target)) {
    session()->flash('success', 'Restored to ' . $target->created_at);
}
```

### Pruning automático

```php
// config/arqel-versioning.php
'keep_versions' => 20,    // mantém só as 20 mais recentes por record
'prune_strategy' => 'count',
```

A poda corre automaticamente após cada novo snapshot. Para forçar
manualmente (ex.: cleanup job):

```php
$article->pruneOldVersions();   // devolve número de rows deletadas
```

## Related

- `PLANNING/10-fase-3-avancadas.md` § "5. Record versioning (VERS)"
- RF-IN-05 (time-travel para records)
- `arqel/audit` — audit log complementar (eventos vs snapshots)
- `arqel/workflow` — pacote vizinho com layout de scaffold idêntico
