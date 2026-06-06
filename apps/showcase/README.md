# Arqel Showcase

The comprehensive **dogfooding** app: a single Laravel admin panel that
exercises the entire Arqel ecosystem — all 20 packages — in one place, so the
framework is validated end-to-end by something real rather than unit fixtures.

It ships four Resources (`Post`, `Author`, `Ticket`, `Setting`), a widget
dashboard, multi-tenancy, audit logging, versioning, a workflow state machine,
an offline AI provider and an MCP server, all wired through one `admin` panel.

## What exercises each package

| Package | How the showcase exercises it |
|---|---|
| `arqel-dev/arqel` | The umbrella metapackage pulling the runtime together. |
| `arqel-dev/core` | The `admin` panel + `PanelRegistry` + resource routing (`ArqelServiceProvider`). |
| `arqel-dev/auth` | Login flow (`->login()`); `/admin/login` with `admin@arqel.test`. |
| `arqel-dev/fields` | Text / select / boolean / date fields across the 4 Resources. |
| `arqel-dev/fields-advanced` | Advanced field types (covered by `e2e/04-advanced-fields.spec.ts`). |
| `arqel-dev/form` | Create/edit forms built from each Resource's field schema. |
| `arqel-dev/table` | Index list tables (columns, sorting) for every Resource. |
| `arqel-dev/actions` | Row actions (`edit`, `delete`) + bulk actions (`deleteBulk`). |
| `arqel-dev/nav` | Navigation groups in the sidebar grouping the Resources. |
| `arqel-dev/widgets` | `MainDashboard` registered in `DashboardRegistry`, served at `/admin`. |
| `arqel-dev/tenant` | `Post` uses `BelongsToTenant` (per-tenant scoping) + `<TenantSwitcher>`. |
| `arqel-dev/export` | `ExportAction` (CSV) bulk action on `PostResource`. |
| `arqel-dev/audit` | `Post` uses `LogsActivity` (spatie/laravel-activitylog). |
| `arqel-dev/versioning` | `Post` uses `Versionable` (revision history). |
| `arqel-dev/workflow` | `Ticket` uses `HasWorkflow` with a `status` state machine. |
| `arqel-dev/realtime` | Broadcasting via the `log` broadcaster (`BROADCAST_CONNECTION=log`). |
| `arqel-dev/ai` | Local, deterministic, $0 `StubProvider` (`app/Ai/StubProvider.php`) wired in `config/arqel-ai.php`. |
| `arqel-dev/mcp` | `McpServiceProvider` auto-discovered at boot (smoke); exposes the MCP surface. |
| `arqel-dev/hooks` | React hooks consumed by the Inertia front-end (`@arqel-dev/hooks`). |
| `arqel-dev/marketplace` | Not exercised (needs Stripe — out of scope, no real-money spend). |
| `arqel-dev/cli` | Build-time / scaffolding tooling, not a runtime surface in this app. |

> Note: `marketplace` and `cli` are intentionally not wired here — the
> marketplace requires a real Stripe account (out of scope) and the CLI is dev
> tooling rather than something a running app exercises.

## Run it

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed --force
pnpm install && pnpm --filter @arqel-dev/showcase build
php artisan serve --host=127.0.0.1 --port=8002
```

Login at `http://127.0.0.1:8002/admin/login` with `admin@arqel.test` /
`password`.

## Tests

- `php artisan test` — feature tests (correctness gate over the Resources,
  dashboard, tenancy and advanced fields).
- `pnpm --filter @arqel-dev/showcase test:e2e` — Playwright suite covering
  resources, dashboard, tenant switching and advanced fields (port `8002`).
