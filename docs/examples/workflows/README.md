# Exemplos de Workflows — `arqel/workflow`

Esta pasta contém três exemplos completos e comentados de máquinas de estado construídas com o pacote `arqel/workflow`. Cada um foi escolhido para exercitar um conjunto distinto de features do pacote, juntos eles cobrem ~todas as decisões de design que aparecem em apps Laravel reais.

Use estes exemplos como ponto de partida quando for desenhar seu próprio workflow: copie a estrutura, adapte os estados/transições ao seu domínio, e remova o que não usar.

## Os três exemplos

| Exemplo | Domínio | Foco principal |
|---|---|---|
| [`order-states.md`](./order-states.md) | E-commerce — pedidos | Autorização por papel (`authorizeFor` + Gate), webhooks de transportadora, transição "any-to" (`Cancelled`), idempotência via `metadata` |
| [`article-states.md`](./article-states.md) | CMS editorial — artigos | Fluxo colaborativo humano, rejeição com feedback, autorização 100% via Gate, integração com `arqel/versioning` para snapshots de conteúdo |
| [`subscription-states.md`](./subscription-states.md) | SaaS billing — assinaturas | Transições disparadas por webhooks Stripe, side-effects em cache/quotas, sistema como ator (humanos têm acesso limitado), uso intensivo de `metadata` para auditoria |

## Tabela comparativa de features

| Feature do `arqel/workflow` | order | article | subscription |
|---|:---:|:---:|:---:|
| `WorkflowDefinition` + `HasWorkflow` trait | sim | sim | sim |
| `StateTransitionField` no Resource | sim | sim | sim |
| `StateFilter` na Table | sim | sim | sim |
| Authorization via `authorizeFor()` | sim | — | sim |
| Authorization via Gate (`transition-X-to-Y`) | sim | sim | — |
| Listener de `StateTransitioned` (queued) | sim | sim | sim |
| Transição "any-to-X" (múltiplos `from()`) | sim (`Cancelled`) | — | sim (`Canceled`) |
| Side-effects em side-systems (email/cache/queue) | email | email + jobs | cache + quotas + email + métricas |
| Uso de `metadata` no histórico | `tracking_code`, `webhook_event_id` | `feedback` | `webhook_event_id`, `event_type`, `recovery` |
| Idempotência via `metadata->webhook_event_id` | sim | — | sim (central) |
| Sistema como ator (user `null`) | parcial (webhook delivery) | — | sim (Stripe webhooks) |
| Estado terminal sem retorno | — | sim (`Archived`) | sim (`Canceled`) |
| Integração com `arqel/versioning` | — | sim | — |
| `defaultFilters` na Table | — | sim | sim |
| Validação de domínio na Gate (`filled(...)`) | sim (`refund_reason`) | — | — |

## Padrões transversais que valem ser destacados

**Slugify de transitions.** O `TransitionAuthorizer` deriva o nome da Gate como `transition-{from-slug}-to-{to-slug}`, onde slug é a última parte do FQCN do state, sem o sufixo `State`, em kebab-case. `OrderState\Pending` → `pending`, `ArticleState\InReview` → `in-review`, `SubscriptionState\PastDue` → `past-due`.

**Listener único vs múltiplos.** O exemplo de `article` usa **um listener** com `match($event->to)` para os três casos relevantes — é simples e legível para handlers leves. `subscription` faz o mesmo (um listener para todas as transições) porque os side-effects compartilham serviços (`FeatureFlagCache`, `QuotaManager`). Já `order` separa em listeners distintos (`NotifyCustomerOfShipment`, etc.) porque cada um tem dependências e níveis de criticidade muito diferentes. Não existe regra dura — escolha o que ficar mais legível para seu time.

**Estado vs Action.** Quando você se pega tentado a adicionar uma transição `Archived → Draft` ou `Canceled → Active`, **pare**. A regra é: máquina de estados representa o ciclo de vida natural do recurso. Operações que "ressuscitam" um recurso terminal devem ser **Actions** que criam um novo registro (duplicar artigo, criar nova subscription) — preservando o histórico do antigo.

**Metadata como contrato.** Em todos os três exemplos o `metadata` da transição segue um schema implícito: campos como `webhook_event_id`, `subscription_id`, `feedback`, `tracking_code` aparecem repetidamente. Documente esse schema no SKILL.md do seu app — auditores e desenvolvedores futuros vão te agradecer.

## Como usar estes exemplos

1. Leia o exemplo cujo domínio mais se assemelha ao seu.
2. Copie o model + workflow definition + transition classes para o seu app, adaptando nomes.
3. Decida sua estratégia de autorização (Gate, `authorizeFor`, ou mix) lendo a tabela acima.
4. Escreva os listeners para os side-effects relevantes — comece com um único listener `match()` se forem simples.
5. Adicione o `StateTransitionField` no Resource e o `StateFilter` na Table.
6. Escreva testes Pest 3 para cada transição (ver `packages/workflow/tests/Feature` no monorepo para padrões).

## Veja também

- [`packages/workflow/SKILL.md`](../../../packages/workflow/SKILL.md) — contexto canônico do pacote.
- [`PLANNING/10-fase-3-avancadas.md`](../../../PLANNING/10-fase-3-avancadas.md) §WF-001..WF-010 — tickets que originaram o pacote.
- [`PLANNING/05-api-php.md`](../../../PLANNING/05-api-php.md) §Workflow — referência da API pública.
- [`PLANNING/03-adrs.md`](../../../PLANNING/03-adrs.md) ADR-017 — Authorization UX-only no client.
- [`spatie/laravel-model-states`](https://spatie.be/docs/laravel-model-states) — integração canônica opcional (suggest-only).
