# `arqel-dev/versioning` — API Reference

Namespace `Arqel\Versioning\`. Time travel for Eloquent records: a `Versionable` trait writing a full snapshot of every change to `arqel_versions`, a per-field diff, non-destructive restore, history and restore HTTP endpoints, and retention through an Artisan command plus a queueable job.

Integration with `arqel-dev/core` is **optional** — the trait works standalone and the controllers degrade to `404` when the `ResourceRegistry` is not bound. There is no hard dependency on `spatie/laravel-eventsourcing`.

## `Arqel\Versioning\Concerns\Versionable` (trait)

Opt-in per model. Only models declaring `use Versionable` produce snapshots — there is no global behavior.

| Method | Type | Description |
|---|---|---|
| `bootVersionable()` | `static void` | Registers the `created` / `updating` / `updated` hooks |
| `versions()` | `MorphMany<Version>` | Ordered `created_at desc, id desc` |
| `currentVersion()` | `?Version` | Shortcut for `versions()->first()` |
| `restoreToVersion(int\|Version $version)` | `bool` | Non-destructive restore |
| `pruneOldVersions()` | `int` | Applies count retention for this record; returns rows deleted |

`created` writes the initial snapshot; `updating` captures the dirty diff (filtering `created_at` / `updated_at`); `updated` consumes it and writes a new `Version`. An empty diff early-returns, so a `touch()` never produces a version. The master switch `arqel-versioning.enabled === false` disables every hook.

Snapshots are **cast-aware**: they store `$model->getAttribute($key)` (the value with casts applied) over the same key set as `getAttributes()`. `array`/`json`/`object`/`collection`/`encrypted` casts are stored deserialized, so a restore does not re-encode them. `changes` carries only the diff (`[old, new]` per field), with both sides also cast.

`restoreToVersion()` re-applies casts by calling `setAttribute()` per key rather than `forceFill()` — it still bypasses mass assignment, but reserializes correctly. The subsequent `save()` fires the hook and creates a new `Version`, which makes "undo restore" possible. It returns `false` defensively when the version does not belong to the record.

`pruneOldVersions()` runs automatically after each write. It returns `0` early when `prune_strategy != 'count'`; `keep_versions = 0` means unbounded. The prune predicate filters `versionable_type` by `getMorphClass()`, matching what `associate()` persists and therefore honoring `Relation::enforceMorphMap()`.

The audit user id resolves through any callable in `arqel-versioning.audit_user` — a `'FQCN::method'` string, a `Closure`, or an `[$object, 'method']` array — falling back to `Auth::id()`. A non-int result (or both being null) stores `null`.

## `Arqel\Versioning\Models\Version` (final)

Append-only row on `arqel_versions`. `$timestamps = false`; `payload` and `changes` cast to `array`, `created_at` to `datetime`.

| Method | Type | Description |
|---|---|---|
| `versionable()` | `MorphTo` | The source model |
| `user()` | `?BelongsTo` | Defensive — reads `arqel-versioning.user_model` (default `App\Models\User`) and returns `null` when the class doesn't exist or is not an Eloquent `Model` |

The migration `2026_05_01_000000_create_arqel_versions_table` creates `morphs('versionable')`, JSON `payload`, JSON `changes`, indexed `created_by_user_id`, nullable `reason` and `created_at`.

## `Arqel\Versioning\VersionPresenter` (final readonly)

`VersionPresenter::toArray(Version $version, bool $includePayload = false): array` serializes a version into a JSON-friendly payload:

```
{
    id: int,
    created_at: string|null,          // ISO 8601
    changes_summary: string,
    changes: array<string, [mixed, mixed]>|null,
    user: {id: int, name: string|null}|null,
    is_initial: bool,
    payload?: array<string, mixed>,   // only when $includePayload
}
```

Summaries: `changes === null` → `"Created"`; `[]` → `"No changes"`; one field → singular (`"Changed 1 field: title"`); N fields → plural (`"Changed 5 fields: a, b, c, d, e"`).

`payload` is **not** exposed by default — snapshots can contain PII and secrets, so the controller only includes it on an explicit `?include=payload`.

## HTTP

Both routes are registered with `web` + `auth` middleware.

| Verb | Route | Name | Controller |
|---|---|---|---|
| GET | `/admin/{resource}/{id}/versions` | `arqel.versioning.history` | `Http\Controllers\VersionHistoryController` |
| POST | `/admin/{resource}/{id}/versions/{versionId}/restore` | `arqel.versioning.restore` | `Http\Controllers\VersionRestoreController` |

Both are single-action `final` controllers that resolve the `ResourceRegistry` by FQCN string and return `404` when it isn't bound, and validate via `class_uses_recursive` that the target model uses the trait (`422` otherwise).

Authorization is Policy-aware: the ability (`view` for history, `update` for restore) is enforced when either a named Gate exists (`Gate::define`) **or** a Policy is registered for the model (`Gate::getPolicyFor`) — `Gate::has()` alone never consults Policies. On deny, history returns `403 {message: "Forbidden"}` without leaking the snapshot via `?include=payload`, and restore returns `403` via an `AuthorizationException`. With neither a gate nor a policy (scaffold mode) access is allowed.

`VersionHistoryController` paginates with `?per_page=` (default 20, clamped to `[1, 100]`), eager-loads `with('user')` only when `Version::user()` resolves, and includes `meta.keep_versions` plus `meta.total` in the response.

`VersionRestoreController` returns `404` for an unknown slug, record or version, and for a version belonging to another record. Success is `200 {restored: true, new_version_id: <int>}`; an unexpected failure logs `arqel.versioning.restore_failed` and returns `500 {restored: false, message: …}`.

## Retention

### `Arqel\Versioning\Console\PruneVersionsCommand` (final)

`arqel:versions:prune`, with combinable flags:

| Flag | Effect |
|---|---|
| `--days=N` | Deletes rows with `created_at < now() - N days` |
| `--keep=N` | Keeps the top N rows per `(versionable_type, versionable_id)` |
| *(no flags)* | Uses `arqel-versioning.keep_versions` as the `--keep` default |
| `--dry-run` | Emits `[DRY RUN] would delete <N> rows.` without deleting |

The happy path emits `Pruned <N> version rows.` (verbose). The command is idempotent — running it twice is safe.

### `Arqel\Versioning\Jobs\PruneOldVersionsJob` (final)

`ShouldQueue` + `Dispatchable` + `SerializesModels` wrapper for schedulers and queues. Constructor `(?int $days, ?int $keep)`; `handle()` calls `Artisan::call('arqel:versions:prune', …)`. Serialize/unserialize round-trips preserve the properties.

## Configuration

`config/arqel-versioning.php` exposes `enabled`, `keep_versions`, `prune_strategy`, `audit_user` and `user_model`.

## Example

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
$article->currentVersion();     // Version from the last save
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

## Related

- SKILL: [`packages/versioning/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/versioning/SKILL.md)
- Source: [`packages/versioning/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/versioning/src/)
