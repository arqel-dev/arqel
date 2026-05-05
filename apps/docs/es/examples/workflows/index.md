# Ejemplos de Workflow — `arqel-dev/workflow`

Esta carpeta contiene tres ejemplos completos y anotados de state machines construidos con el paquete `arqel-dev/workflow`. Cada uno fue elegido para ejercitar un subset distinto de las features del paquete; juntos cubren ~todas las decisiones de diseño que aparecen en apps Laravel reales.

Usa estos ejemplos como punto de partida cuando diseñes tu propio Workflow: copia la estructura, adapta estados/transiciones a tu dominio, y elimina lo que no uses.

## Los tres ejemplos

| Ejemplo | Dominio | Foco principal |
|---|---|---|
| [`order-states.md`](./order-states.md) | E-commerce — pedidos | Autorización por roles (`authorizeFor` + Gate), webhooks de transportadora, transición "any-to" (`Cancelled`), idempotencia vía `metadata` |
| [`article-states.md`](./article-states.md) | CMS editorial — artículos | Flujo colaborativo humano, rechazo con feedback, autorización 100% basada en Gate, integración con `arqel-dev/versioning` para snapshots de contenido |
| [`subscription-states.md`](./subscription-states.md) | SaaS billing — suscripciones | Transiciones disparadas por webhooks de Stripe, side-effects en cache/quotas, sistema como actor (humanos con acceso limitado), uso intensivo de `metadata` para auditoría |

## Tabla de comparación de features

| Feature de `arqel-dev/workflow` | order | article | subscription |
|---|:---:|:---:|:---:|
| `WorkflowDefinition` + trait `HasWorkflow` | yes | yes | yes |
| `StateTransitionField` en el Resource | yes | yes | yes |
| `StateFilter` en la Table | yes | yes | yes |
| Autorización vía `authorizeFor()` | yes | — | yes |
| Autorización vía Gate (`transition-X-to-Y`) | yes | yes | — |
| Listener para `StateTransitioned` (queued) | yes | yes | yes |
| Transición "any-to-X" (múltiples `from()`) | yes (`Cancelled`) | — | yes (`Canceled`) |
| Side-effects en sistemas laterales (email/cache/queue) | email | email + jobs | cache + quotas + email + métricas |
| Uso de `metadata` en el history | `tracking_code`, `webhook_event_id` | `feedback` | `webhook_event_id`, `event_type`, `recovery` |
| Idempotencia vía `metadata->webhook_event_id` | yes | — | yes (central) |
| Sistema como actor (user `null`) | parcial (delivery por webhook) | — | yes (webhooks Stripe) |
| Estado terminal sin retorno | — | yes (`Archived`) | yes (`Canceled`) |
| Integración con `arqel-dev/versioning` | — | yes | — |
| `defaultFilters` en la Table | — | yes | yes |
| Validación de dominio en el Gate (`filled(...)`) | yes (`refund_reason`) | — | — |

## Patrones transversales que vale la pena destacar

**Slugificación de transiciones.** `TransitionAuthorizer` deriva el nombre del Gate como `transition-{from-slug}-to-{to-slug}`, donde slug es la última parte del FQCN del estado, sin el sufijo `State`, en kebab-case. `OrderState\Pending` → `pending`, `ArticleState\InReview` → `in-review`, `SubscriptionState\PastDue` → `past-due`.

**Único vs múltiples listeners.** El ejemplo `article` usa **un único listener** con `match($event->to)` para los tres casos relevantes — simple y legible para handlers ligeros. `subscription` hace lo mismo (un listener para todas las transiciones) porque los side-effects comparten servicios (`FeatureFlagCache`, `QuotaManager`). `order`, en cambio, divide en listeners distintos (`NotifyCustomerOfShipment`, etc.) porque cada uno tiene dependencias muy diferentes y niveles de criticidad distintos. No hay regla dura — elige lo que se mantenga más legible para tu equipo.

**State vs Action.** Cuando te encuentres tentado a añadir una transición `Archived → Draft` o `Canceled → Active`, **párate**. La regla es: una state machine representa el ciclo de vida natural del recurso. Las operaciones que "resucitan" un recurso terminal deben ser **Actions** que crean un nuevo registro (duplicar artículo, crear nueva suscripción) — preservando el historial del antiguo.

**Metadata como contrato.** En los tres ejemplos el `metadata` de la transición sigue un schema implícito: campos como `webhook_event_id`, `subscription_id`, `feedback`, `tracking_code` se repiten. Documenta ese schema en el SKILL.md de tu app — los auditores y los desarrolladores futuros te lo agradecerán.

## Cómo usar estos ejemplos

1. Lee el ejemplo cuyo dominio se parezca más al tuyo.
2. Copia el modelo + workflow definition + clases de transición a tu app, adaptando nombres.
3. Decide tu estrategia de autorización (Gate, `authorizeFor` o mix) leyendo la tabla de arriba.
4. Escribe listeners para los side-effects relevantes — empieza con un único listener `match()` si son simples.
5. Añade `StateTransitionField` al Resource y `StateFilter` a la Table.
6. Escribe tests Pest 3 para cada transición (mira `packages/workflow/tests/Feature` en el monorepo para patrones).

## Ver también

- [`packages/workflow/SKILL.md`](../../../packages/workflow/SKILL.md) — el contexto canónico del paquete.
- [`PLANNING/10-fase-3-avancadas.md`](../../../PLANNING/10-fase-3-avancadas.md) §WF-001..WF-010 — tickets que originaron el paquete.
- [`PLANNING/05-api-php.md`](../../../PLANNING/05-api-php.md) §Workflow — referencia de API pública.
- [`PLANNING/03-adrs.md`](../../../PLANNING/03-adrs.md) ADR-017 — La autorización es solo UX en el cliente.
- [`spatie/laravel-model-states`](https://spatie.be/docs/laravel-model-states) — integración canónica opcional (solo sugerida).
