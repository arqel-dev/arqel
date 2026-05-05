# `arqel-dev/actions` — API Reference

Namespace `Arqel\Actions\`. Action base + 4 variantes + concerns + factory.

## `Arqel\Actions\Action` (abstract)

`__construct` é `final`. Subclasses (`RowAction`/`BulkAction`/`ToolbarAction`/`HeaderAction`) só especializam comportamento.

### Constantes tipadas

```php
COLOR_PRIMARY / SECONDARY / SUCCESS / WARNING / DESTRUCTIVE / INFO
VARIANT_FILLED / OUTLINE / GHOST
METHOD_GET / POST / PUT / PATCH / DELETE
```

### Setters fluentes (base)

```php
label(string), icon(string), color(string), variant(string)
action(Closure)         // executa lógica
url(string|Closure, string $method = 'GET')   // XOR com action()
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

## Variantes

| Class | Onde aparece | Recebe |
|---|---|---|
| `RowAction` | linha da table + página detail | `$record: Model` |
| `BulkAction` | toolbar com 1+ selecionado | `$records: Collection` |
| `ToolbarAction` | toolbar (sempre) | — |
| `HeaderAction` | header de create/edit/show | `$record: ?Model` |

### `BulkAction` extras

```php
chunkSize(int)                       // default 100, clamp ≥ 1
deselectRecordsAfterCompletion(bool)
```

`execute(Collection)` itera em chunks, chamando o callback uma vez por chunk.

## `Arqel\Actions\Actions` (final)

Factory de helpers pré-configurados.

| Método | Retorna |
|---|---|
| `Actions::view()` | RowAction → routa para `/show` |
| `Actions::edit()` | RowAction → routa para `/edit` |
| `Actions::delete()` | RowAction destructive com `requiresConfirmation` |
| `Actions::restore()` | RowAction para soft-deletes |
| `Actions::create()` | ToolbarAction → routa para `/create` |
| `Actions::deleteBulk()` | BulkAction destructive com `requiresConfirmation` |

## HTTP

### `Arqel\Actions\Http\Controllers\ActionController` (final)

4 endpoints sob `arqel.actions.{name}`:

| Endpoint | Método |
|---|---|
| `invokeRow` | `POST {panel}/{resource}/{id}/actions/{action}` |
| `invokeHeader` | `POST {panel}/{resource}/{id}/header-actions/{action}` |
| `invokeToolbar` | `POST {panel}/{resource}/actions/{action}` |
| `invokeBulk` | `POST {panel}/{resource}/bulk-actions/{action}` |

Resolve action por nome em `actions()/headerActions()/toolbarActions()/bulkActions()` (todos opt-in via `method_exists`). Autoriza via `Action::canBeExecutedBy`. Valida payload do form modal quando `Action::hasForm()` é true. Bulk fetcha records via `whereIn(getKeyName, ids)`.

## Related

- SKILL: [`packages/actions/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/actions/SKILL.md)
- Conceitos: [`/pt-BR/guide/actions`](/pt-BR/guide/actions)
- Próximo: [`arqel-dev/auth`](/pt-BR/reference/php/auth)
