# `arqel-dev/actions` — Referencia de API

Namespace `Arqel\Actions\`. Action base + 4 variantes + concerns + factory.

## `Arqel\Actions\Action` (abstract)

`__construct` es `final`. Las subclases (`RowAction`/`BulkAction`/`ToolbarAction`/`HeaderAction`) solo especializan el comportamiento.

### Constantes tipadas

```php
COLOR_PRIMARY / SECONDARY / SUCCESS / WARNING / DESTRUCTIVE / INFO
VARIANT_FILLED / OUTLINE / GHOST
METHOD_GET / POST / PUT / PATCH / DELETE
```

### Setters fluidos (base)

```php
label(string), icon(string), color(string), variant(string)
action(Closure)         // executes logic
url(string|Closure, string $method = 'GET')   // XOR with action()
visible(bool|Closure), disabled(bool|Closure), hidden(bool|Closure)
tooltip(string|Closure)
successNotification(string), failureNotification(string)
```

### Setters vía traits

| Trait | Setters |
|---|---|
| `Confirmable` | `requiresConfirmation`, `modalHeading`, `modalDescription`, `modalIcon`, `modalColor`, `modalConfirmationRequiresText`, `modalSubmitLabel`, `modalCancelLabel` |
| `HasForm` | `form(array<Field>)`, `modalSize`, `modalWide` |
| `HasAuthorization` | `authorize(Closure)` |

### Oráculos

```php
isVisibleFor(?Model $record = null): bool
isDisabledFor(?Model $record = null): bool
canBeExecutedBy(?Authenticatable $user, $record = null): bool
hasForm(): bool
getFormFields(): array<Field>
getFormValidationRules(): array
getFormSchemaArray(): array
resolveUrl(?Model $record = null): ?string
execute(?Model $record, array $data = []): mixed
toArray(?Authenticatable $user, $record = null): array
```

## Variantes

| Clase | Dónde aparece | Recibe |
|---|---|---|
| `RowAction` | Fila de Table + página de detalle | `$record: Model` |
| `BulkAction` | Toolbar con 1+ seleccionados | `$records: Collection` |
| `ToolbarAction` | Toolbar (siempre) | — |
| `HeaderAction` | Header de create/edit/show | `$record: ?Model` |

### Extras de `BulkAction`

```php
chunkSize(int)                       // default 100, clamp ≥ 1
deselectRecordsAfterCompletion(bool)
```

`execute(Collection)` itera en chunks, llamando al callback una vez por chunk.

## `Arqel\Actions\Actions` (final)

Factory de helpers preconfigurados.

| Método | Retorna |
|---|---|
| `Actions::view()` | RowAction → enruta a `/show` |
| `Actions::edit()` | RowAction → enruta a `/edit` |
| `Actions::delete()` | RowAction destructiva con `requiresConfirmation` |
| `Actions::restore()` | RowAction para soft-deletes |
| `Actions::create()` | ToolbarAction → enruta a `/create` |
| `Actions::deleteBulk()` | BulkAction destructiva con `requiresConfirmation` |

## HTTP

### `Arqel\Actions\Http\Controllers\ActionController` (final)

4 endpoints bajo `arqel.actions.{name}`:

| Endpoint | Método |
|---|---|
| `invokeRow` | `POST {panel}/{resource}/{id}/actions/{action}` |
| `invokeHeader` | `POST {panel}/{resource}/{id}/header-actions/{action}` |
| `invokeToolbar` | `POST {panel}/{resource}/actions/{action}` |
| `invokeBulk` | `POST {panel}/{resource}/bulk-actions/{action}` |

Resuelve la action por nombre en `actions()/headerActions()/toolbarActions()/bulkActions()` (todos opt-in vía `method_exists`). Autoriza vía `Action::canBeExecutedBy`. Valida el payload del formulario modal cuando `Action::hasForm()` es true. Bulk obtiene los registros vía `whereIn(getKeyName, ids)`.

## Relacionado

- SKILL: [`packages/actions/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/actions/SKILL.md)
- Conceptos: [`/es/guide/actions`](/es/guide/actions)
- Siguiente: [`arqel-dev/auth`](/es/reference/php/auth)
