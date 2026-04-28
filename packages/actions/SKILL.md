# SKILL.md — arqel/actions

> Contexto canónico para AI agents. Estrutura conforme `PLANNING/04-repo-structure.md` §11.

## Purpose

`arqel/actions` fornece as primitivas invocáveis do framework: row actions (botão por linha numa Table), bulk actions (sobre selecção), toolbar/header actions (botões globais), com confirmação opcional (modal), formulário inline (Action que abre um Form modal), authorization gates, notificações de sucesso/falha e (Fase 2) execução em queue com progresso.

## Status

**Entregue (ACTIONS-001..005):**

- `Arqel\Actions\Action` abstract base com fluent API completo (label/icon/color/variant/action/url/visible/disabled/hidden/tooltip/successNotification/failureNotification), execução XOR (callback **ou** url), oracles `isVisibleFor`/`isDisabledFor`/`resolveUrl`, `execute()` invoca callback, `toArray()` serializa com chaves `null` filtradas
- `Concerns\Confirmable` — modal config com constantes `MODAL_COLOR_*`, setters `modalHeading`/`modalDescription`/`modalIcon`/`modalColor`/`modalConfirmationRequiresText`/`modalSubmit/CancelButtonLabel`. Setters semânticos auto-activam `requiresConfirmation`. Cor inválida cai para `destructive`
- `Concerns\HasAuthorization` — `authorize(Closure)` + `canBeExecutedBy(?Authenticatable, $record)`; default true sem callback
- `Types\RowAction`, `Types\ToolbarAction`, `Types\HeaderAction`, `Types\BulkAction` (com `chunkSize` + `deselectRecordsAfterCompletion`; `execute()` faz chunk automático para `Collection`)
- `Actions` factory: `view`/`edit`/`delete`/`restore`/`create`/`deleteBulk` com defaults razoáveis (delete pre-confirms destructive)
- `Concerns\HasForm` — `form(Field[])` (rejeita não-Field), constantes `MODAL_SIZE_*`, `modalWide()`/`modalSize(string)` com fallback para `md` em valores inválidos, `getFormValidationRules()` agrega regras por nome de field, `getFormSchemaArray()` para o payload Inertia
- 30 testes Pest passando

**Por chegar:**

- `ActionExecutor` + `Http\Controllers\ActionController` (ACTIONS-006) — depende de `CORE-006` (`ResourceController`)
- Integração com Table (ACTIONS-007)
- Queue stub para Fase 1 (ACTIONS-009)

## Conventions

- `declare(strict_types=1)` obrigatório
- Actions são **descritivas** — declaram intenção; o `ActionExecutor` (ACTIONS-007) materializa execução
- Action queue **stub apenas** em Fase 1; queue completa com progress reporting fica para Fase 2 (RF-A-06)
- Confirmation modal é responsabilidade do React side; o PHP só serializa `requiresConfirmation: true` + texto

## Anti-patterns

- ❌ DB writes directamente no closure `action()` sem passar pelo `ActionExecutor` — perde lifecycle hooks, authorization e error handling consistentes
- ❌ Forms inline definidos com `arqel/fields` directamente — usa `Arqel\Form\Form` para garantir validação espelhada server/client
- ❌ Actions globalmente registadas — actions vivem dentro de Resources (`actions()` / `bulkActions()` / `headerActions()`)

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §ACTIONS-001..010
- ADRs: [ADR-001](../../PLANNING/03-adrs.md) Inertia-only · [ADR-008](../../PLANNING/03-adrs.md) Pest 3
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §3 Actions
- Source: [`packages/actions/src/`](src/)
