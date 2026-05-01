# SKILL.md — arqel/versioning

## Purpose

Time-travel para Eloquent records do Arqel (cobre RF-IN-05). O pacote
adiciona um trait `Versionable` que grava snapshots completos de cada
mudança em `arqel_versions`, com diff por campo e suporte a restore
não-destrutivo. UI / diff viewer / restore action ficam para tickets
posteriores.

## Status

### Entregue (VERS-001 + VERS-002)

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

### Por chegar

- **VERS-003** — Resource version history tab (PHP + React).
- **VERS-004** — Diff viewer component (React).
- **VERS-005** — Restore action com confirmation modal.
- **VERS-006** — Retention policy + cleanup job (estratégia 'time').
- **VERS-007** — Testes E2E + cobertura ≥85%.
- **VERS-008** — Docs comparativo: versioning vs activity log.

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

## Examples

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
