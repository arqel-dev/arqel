# `arqel-dev/versioning` — Referencia de API

Namespace `Arqel\Versioning\`. Viaje en el tiempo para registros de Eloquent: un trait `Versionable` que escribe un snapshot completo de cada cambio en `arqel_versions`, un diff por field, restauración no destructiva, endpoints HTTP de historial y restauración, y retención mediante un comando de Artisan más un job encolable.

La integración con `arqel-dev/core` es **opcional** — el trait funciona de forma autónoma y los controladores degradan a `404` cuando el `ResourceRegistry` no está vinculado. No hay dependencia dura de `spatie/laravel-eventsourcing`.

## `Arqel\Versioning\Concerns\Versionable` (trait)

Opt-in por modelo. Solo los modelos que declaran `use Versionable` producen snapshots — no hay comportamiento global.

| Método | Tipo | Descripción |
|---|---|---|
| `bootVersionable()` | `static void` | Registra los hooks `created` / `updating` / `updated` |
| `versions()` | `MorphMany<Version>` | Ordenado `created_at desc, id desc` |
| `currentVersion()` | `?Version` | Atajo para `versions()->first()` |
| `restoreToVersion(int\|Version $version)` | `bool` | Restauración no destructiva |
| `pruneOldVersions()` | `int` | Aplica la retención por conteo a este registro; devuelve las filas eliminadas |

`created` escribe el snapshot inicial; `updating` captura el diff sucio (filtrando `created_at` / `updated_at`); `updated` lo consume y escribe una nueva `Version`. Un diff vacío retorna temprano, así que un `touch()` nunca produce una versión. El interruptor maestro `arqel-versioning.enabled === false` deshabilita todos los hooks.

Los snapshots son **conscientes de los casts**: almacenan `$model->getAttribute($key)` (el valor con los casts aplicados) sobre el mismo conjunto de claves que `getAttributes()`. Los casts `array`/`json`/`object`/`collection`/`encrypted` se almacenan deserializados, de modo que una restauración no los vuelve a codificar. `changes` lleva solo el diff (`[old, new]` por field), con ambos lados también casteados.

`restoreToVersion()` reaplica los casts llamando a `setAttribute()` por clave en lugar de `forceFill()` — sigue saltándose el mass assignment, pero reserializa correctamente. El `save()` posterior dispara el hook y crea una nueva `Version`, lo que hace posible "deshacer la restauración". Devuelve `false` de forma defensiva cuando la versión no pertenece al registro.

`pruneOldVersions()` se ejecuta automáticamente tras cada escritura. Devuelve `0` de inmediato cuando `prune_strategy != 'count'`; `keep_versions = 0` significa sin límite. El predicado de poda filtra `versionable_type` por `getMorphClass()`, coincidiendo con lo que persiste `associate()` y respetando por tanto `Relation::enforceMorphMap()`.

El id del usuario de auditoría se resuelve mediante cualquier callable en `arqel-versioning.audit_user` — una cadena `'FQCN::method'`, una `Closure`, o un array `[$object, 'method']` — recayendo en `Auth::id()`. Un resultado que no sea int (o ambos siendo null) almacena `null`.

## `Arqel\Versioning\Models\Version` (final)

Fila append-only en `arqel_versions`. `$timestamps = false`; `payload` y `changes` casteados a `array`, `created_at` a `datetime`.

| Método | Tipo | Descripción |
|---|---|---|
| `versionable()` | `MorphTo` | El modelo de origen |
| `user()` | `?BelongsTo` | Defensivo — lee `arqel-versioning.user_model` (por defecto `App\Models\User`) y devuelve `null` cuando la clase no existe o no es un `Model` de Eloquent |

La migración `2026_05_01_000000_create_arqel_versions_table` crea `morphs('versionable')`, `payload` JSON, `changes` JSON, `created_by_user_id` indexado, `reason` nulable y `created_at`.

## `Arqel\Versioning\VersionPresenter` (final readonly)

`VersionPresenter::toArray(Version $version, bool $includePayload = false): array` serializa una versión en un payload compatible con JSON:

```
{
    id: int,
    created_at: string|null,          // ISO 8601
    changes_summary: string,
    changes: array<string, [mixed, mixed]>|null,
    user: {id: int, name: string|null}|null,
    is_initial: bool,
    payload?: array<string, mixed>,   // solo cuando $includePayload
}
```

Resúmenes: `changes === null` → `"Created"`; `[]` → `"No changes"`; un field → singular (`"Changed 1 field: title"`); N fields → plural (`"Changed 5 fields: a, b, c, d, e"`).

`payload` **no** se expone por defecto — los snapshots pueden contener PII y secretos, así que el controlador solo lo incluye ante un `?include=payload` explícito.

## HTTP

Ambas rutas se registran con los middleware `web` + `auth`.

| Verbo | Ruta | Nombre | Controlador |
|---|---|---|---|
| GET | `/admin/{resource}/{id}/versions` | `arqel.versioning.history` | `Http\Controllers\VersionHistoryController` |
| POST | `/admin/{resource}/{id}/versions/{versionId}/restore` | `arqel.versioning.restore` | `Http\Controllers\VersionRestoreController` |

Ambos son controladores `final` de acción única que resuelven el `ResourceRegistry` por su cadena FQCN y devuelven `404` cuando no está vinculado, y validan vía `class_uses_recursive` que el modelo destino usa el trait (`422` en caso contrario).

La autorización es consciente de las Policies: la ability (`view` para el historial, `update` para la restauración) se aplica cuando existe un Gate con nombre (`Gate::define`) **o** hay una Policy registrada para el modelo (`Gate::getPolicyFor`) — `Gate::has()` por sí solo nunca consulta las Policies. Al denegar, el historial devuelve `403 {message: "Forbidden"}` sin filtrar el snapshot vía `?include=payload`, y la restauración devuelve `403` mediante una `AuthorizationException`. Sin gate ni policy (modo scaffold) el acceso se permite.

`VersionHistoryController` pagina con `?per_page=` (por defecto 20, acotado a `[1, 100]`), hace eager loading de `with('user')` solo cuando `Version::user()` resuelve, e incluye `meta.keep_versions` más `meta.total` en la respuesta.

`VersionRestoreController` devuelve `404` para un slug, registro o versión desconocidos, y para una versión que pertenece a otro registro. El éxito es `200 {restored: true, new_version_id: <int>}`; un fallo inesperado registra `arqel.versioning.restore_failed` y devuelve `500 {restored: false, message: …}`.

## Retención

### `Arqel\Versioning\Console\PruneVersionsCommand` (final)

`arqel:versions:prune`, con flags combinables:

| Flag | Efecto |
|---|---|
| `--days=N` | Elimina las filas con `created_at < now() - N days` |
| `--keep=N` | Conserva las N primeras filas por `(versionable_type, versionable_id)` |
| *(sin flags)* | Usa `arqel-versioning.keep_versions` como valor por defecto de `--keep` |
| `--dry-run` | Emite `[DRY RUN] would delete <N> rows.` sin eliminar nada |

La vía feliz emite `Pruned <N> version rows.` (verbose). El comando es idempotente — ejecutarlo dos veces es seguro.

### `Arqel\Versioning\Jobs\PruneOldVersionsJob` (final)

Envoltorio `ShouldQueue` + `Dispatchable` + `SerializesModels` para schedulers y colas. Constructor `(?int $days, ?int $keep)`; `handle()` llama a `Artisan::call('arqel:versions:prune', …)`. Los round-trips de serialización/deserialización preservan las propiedades.

## Configuración

`config/arqel-versioning.php` expone `enabled`, `keep_versions`, `prune_strategy`, `audit_user` y `user_model`.

## Ejemplo

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
$article->currentVersion();     // Version del último save
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

## Relacionado

- SKILL: [`packages/versioning/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/versioning/SKILL.md)
- Código fuente: [`packages/versioning/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/versioning/src/)
