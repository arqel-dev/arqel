# `arqel-dev/actions` — API Reference

Namespace `Arqel\Actions\`. Action base + 4 variants + concerns + factory.

## `Arqel\Actions\Action` (abstract)

`__construct` is `final`. Subclasses (`RowAction`/`BulkAction`/`ToolbarAction`/`HeaderAction`) only specialize behavior.

### Typed constants

```php
COLOR_PRIMARY / SECONDARY / SUCCESS / WARNING / DESTRUCTIVE / INFO
VARIANT_FILLED / OUTLINE / GHOST
METHOD_GET / POST / PUT / PATCH / DELETE
```

### Fluent setters (base)

```php
label(string), icon(string), color(string), variant(string)
action(Closure)         // executes logic
url(string|Closure, string $method = 'GET')   // XOR with action()
visible(bool|Closure), disabled(bool|Closure), hidden(bool|Closure)
tooltip(string|Closure)
successNotification(string), failureNotification(string)
```

### Setters via traits

| Trait | Setters |
|---|---|
| `Confirmable` | `requiresConfirmation`, `modalHeading`, `modalDescription`, `modalIcon`, `modalColor`, `modalConfirmationRequiresText`, `modalSubmitLabel`, `modalCancelLabel` |
| `HasForm` | `form(array<Field>)`, `modalSize`, `modalWide` |
| `HasAuthorization` | `authorize(Closure)` |

### Oracles

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

## Variants

| Class | Where it shows up | Receives |
|---|---|---|
| `RowAction` | Table row + detail page | `$record: Model` |
| `BulkAction` | Toolbar with 1+ selected | `$records: Collection` |
| `ToolbarAction` | Toolbar (always) | — |
| `HeaderAction` | Header of create/edit/show | `$record: ?Model` |

### `BulkAction` extras

```php
chunkSize(int)                       // default 100, clamp ≥ 1
deselectRecordsAfterCompletion(bool)
```

`execute(Collection)` iterates in chunks, calling the callback once per chunk.

## `Arqel\Actions\Actions` (final)

Factory of preconfigured helpers.

| Method | Returns |
|---|---|
| `Actions::view()` | RowAction → routes to `/show` |
| `Actions::edit()` | RowAction → routes to `/edit` |
| `Actions::delete()` | Destructive RowAction with `requiresConfirmation` |
| `Actions::restore()` | RowAction for soft-deletes |
| `Actions::create()` | ToolbarAction → routes to `/create` |
| `Actions::deleteBulk()` | Destructive BulkAction with `requiresConfirmation` |

## HTTP

### `Arqel\Actions\Http\Controllers\ActionController` (final)

4 endpoints under `arqel.actions.{name}`:

| Endpoint | Method |
|---|---|
| `invokeRow` | `POST {panel}/{resource}/{id}/actions/{action}` |
| `invokeHeader` | `POST {panel}/{resource}/{id}/header-actions/{action}` |
| `invokeToolbar` | `POST {panel}/{resource}/actions/{action}` |
| `invokeBulk` | `POST {panel}/{resource}/bulk-actions/{action}` |

Resolves the action by name in `actions()/headerActions()/toolbarActions()/bulkActions()` (all opt-in via `method_exists`). Authorizes via `Action::canBeExecutedBy`. Validates the modal form payload when `Action::hasForm()` is true. Bulk fetches records via `whereIn(getKeyName, ids)`.

## Related

- SKILL: [`packages/actions/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/actions/SKILL.md)
- Concepts: [`/guide/actions`](/guide/actions)
- Next: [`arqel-dev/auth`](/reference/php/auth)
