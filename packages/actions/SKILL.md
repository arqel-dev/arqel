# SKILL.md — arqel/actions

> Contexto canónico para AI agents. Estrutura conforme `PLANNING/04-repo-structure.md` §11.

## Purpose

`arqel/actions` fornece as primitivas invocáveis do framework: row actions (botão por linha numa Table), bulk actions (sobre selecção), toolbar/header actions (botões globais), com confirmação opcional (modal), formulário inline (Action que abre um Form modal), authorization gates, notificações de sucesso/falha e (Fase 2) execução em queue com progresso.

## Status (ACTIONS-001)

Apenas o esqueleto:

- `composer.json` com deps em `arqel/core: @dev` + `arqel/fields: @dev` + `arqel/form: @dev`
- `ActionsServiceProvider` registado via auto-discovery
- PSR-4 `Arqel\Actions\` → `src/`
- Smoke tests (provider boot + namespace autoload)

Ainda **NÃO existem** (chegam em ACTIONS-002+):

- `Arqel\Actions\Action` abstract base
- `Types/` concretos: `RowAction`, `BulkAction`, `ToolbarAction`, `HeaderAction`
- `Concerns/`: `Confirmable`, `HasForm`, `HasAuthorization`, `HasQueuing`
- `ActionExecutor` orquestrador
- `Http/Controllers/ActionController`

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
