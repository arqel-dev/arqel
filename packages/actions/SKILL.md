# SKILL.md — arqel-dev/actions

> Contexto canónico para AI agents. Estrutura conforme `PLANNING/04-repo-structure.md` §11.

## Purpose

`arqel-dev/actions` fornece as primitivas invocáveis do framework: row actions (botão por linha numa Table), bulk actions (sobre selecção), toolbar/header actions (botões globais ou de detail page), com confirmação opcional (modal), formulário inline (Action que abre um Form modal com Fields), authorization gates, notificações de sucesso/falha.

## Status

**Entregue (ACTIONS-001..008):**

- `Arqel\Actions\Action` abstract base com fluent API completo, oracles, XOR action↔url
- `Concerns\Confirmable` — modal de confirmação (cores destructive/warning/info, type-to-confirm, auto-active flags)
- `Concerns\HasAuthorization` — gates per-action: `authorize()` aceita **Closure** `(?Authenticatable, mixed) => bool` **ou** uma **string Gate-ability** (`->authorize('refund')`, v0.14.0). A string checa `Gate::forUser($user)->allows($ability, $record)` contra o record bound (`null` para actions record-less). Closure e string **compõem em AND** (ambas têm de passar); declarar nenhuma mantém o default permissivo (sempre autorizado) — o gate de resource (`update`/`viewAny`) segue como guarda externa. **Não** vira deny-by-default — só **adiciona** um mecanismo explícito de Policy per-action
- `Concerns\HasForm` — form modal com fields + size + validation
- `Types\RowAction`, `Types\ToolbarAction`, `Types\HeaderAction`, `Types\BulkAction` (chunking automático via `chunkSize`)
- `Actions` factory para verbos comuns (view/edit/delete/restore/create/deleteBulk)
- `Http\Controllers\ActionController` (ACTIONS-006) — 4 métodos `invokeRow/invokeHeader/invokeToolbar/invokeBulk`. Resolve Resource via `ResourceRegistry::findBySlug`, action por nome em coleções duck-typed (`actions/headerActions/toolbarActions/bulkActions`), autoriza via `canBeExecutedBy`, valida payload do form modal, executa callback e flasha success/failure (ACTIONS-006). **Não registra rotas próprias** (#174): o despacho de actions passa pelas rotas de `arqel-dev/core` — row view/edit/delete/restore via `arqel.resources.*` (`/{painel}/{resource}/{id}…`), bulk via `arqel.resources.bulk` (`/{painel}/{resource}/bulk/{action}` → `ResourceController::bulkAction`, #48), custom **com `->action(Closure)`** via `arqel.resources.action` (`POST /{painel}/{resource}/actions/{name}[/{id}]` → `ResourceController::rowAction`, #231), e custom-link via o `->url(...)` explícito da Action (ver `Action::resolveStockUrl()`). Essas rotas de core carregam o stack de middleware do painel/config (web + auth + tenant). As rotas standalone `arqel.actions.*` foram removidas — duplicavam o despacho e divergiam no middleware (só `web`)
- Integração com Table — `InertiaDataBuilder::serializeMany` agora aceita `?Authenticatable $user` e via `ReflectionMethod::getNumberOfParameters` passa-o para `Action::toArray($user, $record)` quando a assinatura aceita; permite resolução user-aware de `disabled`/`url` no payload. Per-row visibility via `arqel.actions: ['view','edit']` lista os nomes habilitados para `(user, record)` cada record (ACTIONS-007)
- Per-record URL/disabled (#140) — actions de tabela são serializadas **uma vez** com `$record=null` (definição compartilhada), mas `url(Closure)`/`disabled(Closure)` dependem da linha. `InertiaDataBuilder::serializeRecord` emite por registro `arqel.actionOverrides: {actionName: {url?, disabled?}}` resolvido contra a linha real, **só** para actions record-dependent (detectadas via `Action::hasRecordDependentUrl()`/`hasRecordDependentDisabled()`). Stock `{id}`-template e URL estática não geram override (payload enxuto). O frontend mescla os overrides sobre a definição de tabela por linha
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
- `hasRecordDependentUrl(): bool` / `hasRecordDependentDisabled(): bool` — `true` quando `url`/`disabled` é uma `Closure` (precisa resolver por linha, #140)
- `canBeExecutedBy(?Authenticatable, ?$record): bool` — avalia o `authorize` (Closure e/ou Gate-ability string compõem em AND; default true)
- `authorize(Closure|string): static` — Closure custom **ou** uma string de Gate-ability (`->authorize('refund')`, v0.14.0). Os dois caminhos coexistem e compõem em AND
- `execute(?$record, array $data): mixed` — invoca callback (returna `null` sem callback)
- `toArray(?Authenticatable, ?$record, ?object $resource): array<string, mixed>` — payload Inertia (chaves null filtradas). Quando o action não declarou `->url()` e um `$resource` (com `::$slug`) é passado, `resolveStockUrl()` emite a URL convencional: (a) sem callback — row `view/edit/delete/restore` em `/admin/{slug}/{id}[/...]` e **qualquer** action `type==='bulk'` (não só `delete`) em `POST /admin/{slug}/bulk/{name}` (#48); (b) **com `->action(Closure)`** — custom row/header em `POST /admin/{slug}/actions/{name}/{id}` e custom toolbar (sem record) em `POST /admin/{slug}/actions/{name}` (#231), apontando para `ResourceController::rowAction`. Assim toda action despachável carrega uma `url` e o frontend nunca recai num route inexistente (a `/arqel-dev/actions/{name}` morta removida no #174). Sem `$resource` (serialização de tabela sem slug) nenhuma url stock é emitida — compatível com o contrato anterior. Quando o action tem form, o payload também carrega `formFields` — a FieldSchema completa de cada field via `FieldSchemaSerializer($user)` — ao lado do schema de layout `form` (#213)

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

`Action::toArray()` envia o form em **duas** chaves complementares (#213): `form` é o schema de **layout** (`[{name,type}]`, a ordem de render), e `formFields` é a **FieldSchema completa** de cada field — a mesma produzida pelo `FieldSchemaSerializer` (CORE-010) para o form normal de um Resource, com options resolvidas, label, placeholder, validação e props por-tipo. O React (`<ActionFormModal>`) junta as duas por `name`, então o select renderiza suas opções e os fields suas labels (sem isto o modal abriria vazio). Action forms não têm record/resource owner, logo options/rotas de relacionamento que dependem de owner ficam a cargo dos props estáticos do field.

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

### Authorization per-action (Policy ou Closure)

```php
// Gate-ability string → Gate::allows('refund', $record) (Policy per-action):
RowAction::make('refund')
    ->authorize('refund')
    ->action(fn ($order) => $order->refund());

// Closure custom (predicado livre):
RowAction::make('archive')
    ->authorize(fn ($user, $record) => $user?->isAdmin() && ! $record->archived)
    ->action(fn ($record) => $record->archive());

// Ambos compõem em AND — as duas têm de passar:
RowAction::make('escalate')
    ->authorize('escalate')                       // Gate-ability
    ->authorize(fn ($user) => $user?->isManager()) // + predicado
    ->action(fn ($r) => $r->escalate());
```

`canBeExecutedBy()` é o gate **adicional** ao Resource Policy (`update`/`viewAny`), invocado pelo `ResourceController::rowAction` (#231) — uma negação devolve 403.

### Action como link externo

```php
RowAction::make('view_on_site')
    ->icon('arrow-top-right-on-square')
    ->url(fn ($post) => route('public.posts.show', $post), 'GET');
```

## Conventions

- `declare(strict_types=1)` obrigatório
- `Action::__construct` é `final`; subclasses só declaram `$type`. Para extensibilidade usa **traits/concerns** ou mais um Type final
- Form fields dentro de actions usam `arqel-dev/fields` directamente (sem layout components — modais são intencionalmente simples)
- Authorization é gate **adicional** ao Resource Policy — não substitui. `authorize()` aceita uma **Closure** ou uma **string de Gate-ability** (`->authorize('refund')` → `Gate::allows('refund', $record)`); ambas compõem em AND. É para regras específicas ao action; o Resource Policy continua a ser invocado via Laravel Gate
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
