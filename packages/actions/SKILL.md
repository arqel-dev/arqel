# SKILL.md — arqel/actions

> Contexto canónico para AI agents. Estrutura conforme `PLANNING/04-repo-structure.md` §11.

## Purpose

`arqel/actions` fornece as primitivas invocáveis do framework: row actions (botão por linha numa Table), bulk actions (sobre selecção), toolbar/header actions (botões globais ou de detail page), com confirmação opcional (modal), formulário inline (Action que abre um Form modal com Fields), authorization gates, notificações de sucesso/falha.

## Status

**Entregue (ACTIONS-001..008):**

- `Arqel\Actions\Action` abstract base com fluent API completo, oracles, XOR action↔url
- `Concerns\Confirmable` — modal de confirmação (cores destructive/warning/info, type-to-confirm, auto-active flags)
- `Concerns\HasAuthorization` — gates per-action via Closure `(?Authenticatable, mixed) => bool`
- `Concerns\HasForm` — form modal com fields + size + validation
- `Types\RowAction`, `Types\ToolbarAction`, `Types\HeaderAction`, `Types\BulkAction` (chunking automático via `chunkSize`)
- `Actions` factory para verbos comuns (view/edit/delete/restore/create/deleteBulk)
- `Http\Controllers\ActionController` (ACTIONS-006) — 4 endpoints `invokeRow/invokeHeader/invokeToolbar/invokeBulk` sob `arqel.actions.{name}`. Resolve Resource via `ResourceRegistry::findBySlug`, action por nome em coleções duck-typed (`actions/headerActions/toolbarActions/bulkActions`), autoriza via `canBeExecutedBy`, valida payload do form modal, executa callback e flasha success/failure (ACTIONS-006)
- Integração com Table — `InertiaDataBuilder::serializeMany` agora aceita `?Authenticatable $user` e via `ReflectionMethod::getNumberOfParameters` passa-o para `Action::toArray($user, $record)` quando a assinatura aceita; permite resolução user-aware de `disabled`/`url` no payload. Per-row visibility via `arqel.actions: ['view','edit']` lista os nomes habilitados para `(user, record)` cada record (ACTIONS-007)
- **49 testes Pest passando** (era 30): cobertura nova de `Confirmable` (8 tests), `HasAuthorization` (4 tests), `ActionController` Feature (7 tests cobrindo 404 slug, 404 action name, success notification, deny via authorize → 403, failure notification, 422 sem ids[], duck-typed collection lookup) (ACTIONS-008)

**Por chegar:**

- Queue helper full com progress real-time (Fase 2, RF-A-06)
- Bulk per-record authorization (ACTIONS-007 follow-up Phase 2)
- Feature tests end-to-end com DB (`bulk delete de 50 users`) — bloqueado por `pdo_sqlite` driver no host

## Key Contracts

### `Action` abstract

```php
RowAction::make('publish')
    ->label('Publish')
    ->icon('check')
    ->color(Action::COLOR_SUCCESS)            // primary|secondary|destructive|success|warning|info
    ->variant(Action::VARIANT_OUTLINE)        // default|outline|ghost|destructive
    ->tooltip('Publish this record')
    ->visible(fn ($r) => ! $r->published)
    ->disabled(fn ($r) => $r->locked)
    ->successNotification('Published.')
    ->failureNotification('Could not publish.')
    ->action(fn ($record, $data) => $record->publish());
```

XOR: `action(Closure)` define um callback PHP; `url(Closure|string, method)` faz o action virar um link. Os dois setters limpam o outro — não coexistem.

Oracles públicos:
- `isVisibleFor(?$record): bool` — `hidden` flag, depois `visible` closure
- `isDisabledFor(?$record): bool` — `disabled` closure
- `resolveUrl(?$record): ?string` — string literal ou closure resolvida
- `canBeExecutedBy(?Authenticatable, ?$record): bool` — `authorize` closure (default true)
- `execute(?$record, array $data): mixed` — invoca callback (returna `null` sem callback)
- `toArray(?Authenticatable, ?$record): array<string, mixed>` — payload Inertia (chaves null filtradas)

### Tipos concretos

| Classe | Type | Uso típico |
|---|---|---|
| `RowAction` | `row` | Botão por linha. Callback recebe `(record, data)` |
| `ToolbarAction` | `toolbar` | Botão global acima da tabela. Sem record |
| `HeaderAction` | `header` | Botão na header de uma detail page. Recebe record |
| `BulkAction` | `bulk` | Sobre selecção. Callback recebe `Collection` por chunk; `chunkSize(100)` por defeito; `deselectRecordsAfterCompletion(true)` por defeito |

### `Confirmable`

```php
RowAction::make('delete')
    ->requiresConfirmation()
    ->modalHeading('Delete record?')
    ->modalDescription('This action cannot be undone.')
    ->modalColor(Action::MODAL_COLOR_DESTRUCTIVE)   // destructive|warning|info
    ->modalConfirmationRequiresText('DELETE')       // user precisa digitar para confirmar
    ->modalSubmitButtonLabel('Delete')
    ->modalCancelButtonLabel('Cancel');
```

`modalHeading`/`modalDescription`/`modalConfirmationRequiresText` auto-activam `requiresConfirmation`. Cor inválida cai para `destructive`.

### `HasForm`

```php
RowAction::make('transfer')
    ->modalSize(Action::MODAL_SIZE_LG)              // sm|md|lg|xl|full
    ->form([
        (new SelectField('new_owner'))
            ->options(fn () => User::pluck('name', 'id')->all())
            ->required(),
        (new TextField('reason'))->required(),
    ])
    ->action(fn ($record, $data) => $record->transferTo($data['new_owner'], $data['reason']));
```

O controller (ACTIONS-006) valida o request com `getFormValidationRules()` antes de chamar `execute()`. Entries não-Field passados a `form([...])` são descartados graciosamente.

### `Actions` factory (built-ins)

```php
use Arqel\Actions\Actions;

Actions::view();         // RowAction com icon=eye, color=secondary, variant=ghost
Actions::edit();         // RowAction com icon=pencil, color=primary
Actions::delete();       // RowAction destructive, pre-configured com confirmation modal
Actions::restore();      // RowAction com icon=arrow-uturn-left, color=success
Actions::create();       // ToolbarAction com icon=plus, color=primary
Actions::deleteBulk();   // BulkAction destructive com confirmation
```

Todos retornam instâncias mutáveis — encadeia setters para customizar.

## Examples

### Action simples com callback

```php
RowAction::make('publish')
    ->color(Action::COLOR_SUCCESS)
    ->successNotification('Post published.')
    ->action(fn ($post) => $post->publish());
```

### Action destructive com confirmação

```php
RowAction::make('delete')
    ->color(Action::COLOR_DESTRUCTIVE)
    ->variant(Action::VARIANT_GHOST)
    ->requiresConfirmation()
    ->modalHeading('Delete record?')
    ->modalDescription('This action cannot be undone.')
    ->action(fn ($record) => $record->delete());
```

### Bulk action com chunking

```php
BulkAction::make('archive')
    ->chunkSize(50)
    ->action(function (\Illuminate\Support\Collection $records) {
        foreach ($records as $record) {
            $record->update(['archived_at' => now()]);
        }
    });
```

### Action com form modal

```php
RowAction::make('change_status')
    ->form([
        (new SelectField('status'))->options([
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived',
        ])->required(),
        (new TextField('reason'))->nullable(),
    ])
    ->action(fn ($post, $data) => $post->update($data));
```

### Action como link externo

```php
RowAction::make('view_on_site')
    ->icon('arrow-top-right-on-square')
    ->url(fn ($post) => route('public.posts.show', $post), 'GET');
```

## Conventions

- `declare(strict_types=1)` obrigatório
- `Action::__construct` é `final`; subclasses só declaram `$type`. Para extensibilidade usa **traits/concerns** ou mais um Type final
- Form fields dentro de actions usam `arqel/fields` directamente (sem layout components — modais são intencionalmente simples)
- Authorization é gate **adicional** ao Resource Policy — não substitui. `authorize()` é para regras especificas ao action; o Resource Policy continua a ser invocado via Laravel Gate
- Notificações usam `successNotification`/`failureNotification` para texto literal — i18n é responsabilidade do consumer (passa string já traduzida)

## Anti-patterns

- ❌ **Múltiplas escritas DB no callback sem transação** — usa `DB::transaction(fn () => ...)` dentro do action; sem isso, falha parcial deixa state inconsistente
- ❌ **Side effects fora-de-banda no callback** (envio de email, webhook) — pertence a queue. Em Fase 1 dispare `Mail::queue(...)` ou `Bus::dispatch(...)` em vez de fazer síncrono
- ❌ **`->action()` E `->url()`** — XOR conceitual; o último ganha (limpa o outro). Decide explicitamente
- ❌ **Form fields com layout components** (`Section`, `Grid`) — não é suportado em actions; modais devem ser uma lista flat de fields. Para forms ricos usa `Arqel\Form\Form`
- ❌ **Notificações longas** — `successNotification('Done.')` cabe num toast; mensagens longas pertencem a uma flash session ou redirect
- ❌ **`requiresConfirmation()` sem `modalHeading`** — funciona, mas a UI fica confusa. Sempre fornece heading

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §ACTIONS-001..010
- ADRs: [ADR-001](../../PLANNING/03-adrs.md) Inertia-only · [ADR-008](../../PLANNING/03-adrs.md) Pest 3
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §3 Actions
- Source: [`packages/actions/src/`](src/)
- Tests: [`packages/actions/tests/`](tests/)
