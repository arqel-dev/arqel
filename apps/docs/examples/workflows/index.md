# Workflow examples — `arqel-dev/workflow`

This folder contains three complete and annotated state machine examples built with the `arqel-dev/workflow` package. Each one was chosen to exercise a distinct subset of the package's features; together they cover ~all the design decisions that show up in real Laravel apps.

Use these examples as a starting point when you design your own workflow: copy the structure, adapt states/transitions to your domain, and remove what you don't use.

## The three examples

| Example | Domain | Main focus |
|---|---|---|
| [`order-states.md`](./order-states.md) | E-commerce — orders | Role-based authorization (`authorizeFor` + Gate), carrier webhooks, "any-to" transition (`Cancelled`), idempotency via `metadata` |
| [`article-states.md`](./article-states.md) | CMS editorial — articles | Human collaborative flow, rejection with feedback, 100% Gate-based authorization, integration with `arqel-dev/versioning` for content snapshots |
| [`subscription-states.md`](./subscription-states.md) | SaaS billing — subscriptions | Transitions triggered by Stripe webhooks, side-effects in cache/quotas, system as actor (humans have limited access), heavy use of `metadata` for auditing |

## Feature comparison table

| `arqel-dev/workflow` feature | order | article | subscription |
|---|:---:|:---:|:---:|
| `WorkflowDefinition` + `HasWorkflow` trait | yes | yes | yes |
| `StateTransitionField` on the Resource | yes | yes | yes |
| `StateFilter` on the Table | yes | yes | yes |
| Authorization via `authorizeFor()` | yes | — | yes |
| Authorization via Gate (`transition-X-to-Y`) | yes | yes | — |
| Listener for `StateTransitioned` (queued) | yes | yes | yes |
| "any-to-X" transition (multiple `from()`) | yes (`Cancelled`) | — | yes (`Canceled`) |
| Side-effects in side-systems (email/cache/queue) | email | email + jobs | cache + quotas + email + metrics |
| Use of `metadata` in history | `tracking_code`, `webhook_event_id` | `feedback` | `webhook_event_id`, `event_type`, `recovery` |
| Idempotency via `metadata->webhook_event_id` | yes | — | yes (central) |
| System as actor (user `null`) | partial (webhook delivery) | — | yes (Stripe webhooks) |
| Terminal state with no return | — | yes (`Archived`) | yes (`Canceled`) |
| Integration with `arqel-dev/versioning` | — | yes | — |
| `defaultFilters` on the Table | — | yes | yes |
| Domain validation in the Gate (`filled(...)`) | yes (`refund_reason`) | — | — |

## Cross-cutting patterns worth highlighting

**Slugifying transitions.** `TransitionAuthorizer` derives the Gate name as `transition-{from-slug}-to-{to-slug}`, where slug is the last part of the state's FQCN, without the `State` suffix, in kebab-case. `OrderState\Pending` → `pending`, `ArticleState\InReview` → `in-review`, `SubscriptionState\PastDue` → `past-due`.

**Single vs multiple listeners.** The `article` example uses **a single listener** with `match($event->to)` for the three relevant cases — simple and readable for lightweight handlers. `subscription` does the same (one listener for all transitions) because the side-effects share services (`FeatureFlagCache`, `QuotaManager`). `order`, on the other hand, splits into distinct listeners (`NotifyCustomerOfShipment`, etc.) because each has very different dependencies and criticality levels. There's no hard rule — pick whichever stays most readable for your team.

**State vs Action.** When you find yourself tempted to add a `Archived → Draft` or `Canceled → Active` transition, **stop**. The rule is: a state machine represents the resource's natural lifecycle. Operations that "resurrect" a terminal resource should be **Actions** that create a new record (duplicate article, create new subscription) — preserving the old one's history.

**Metadata as a contract.** In all three examples the transition's `metadata` follows an implicit schema: fields like `webhook_event_id`, `subscription_id`, `feedback`, `tracking_code` recur. Document that schema in your app's SKILL.md — auditors and future developers will thank you.

## How to use these examples

1. Read the example whose domain most resembles yours.
2. Copy the model + workflow definition + transition classes into your app, adapting names.
3. Decide your authorization strategy (Gate, `authorizeFor`, or mix) by reading the table above.
4. Write listeners for the relevant side-effects — start with a single `match()` listener if they're simple.
5. Add `StateTransitionField` to the Resource and `StateFilter` to the Table.
6. Write Pest 3 tests for each transition (see `packages/workflow/tests/Feature` in the monorepo for patterns).

## See also

- [`packages/workflow/SKILL.md`](../../../packages/workflow/SKILL.md) — the package's canonical context.
- [`PLANNING/10-fase-3-avancadas.md`](../../../PLANNING/10-fase-3-avancadas.md) §WF-001..WF-010 — tickets that originated the package.
- [`PLANNING/05-api-php.md`](../../../PLANNING/05-api-php.md) §Workflow — public API reference.
- [`PLANNING/03-adrs.md`](../../../PLANNING/03-adrs.md) ADR-017 — Authorization is UX-only on the client.
- [`spatie/laravel-model-states`](https://spatie.be/docs/laravel-model-states) — optional canonical integration (suggest-only).
