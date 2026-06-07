# apps/showcase — comprehensive dogfooding app — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `apps/showcase`, a Laravel app exercising all 20 `arqel-dev/*` packages, as a known-good baseline that the autonomous dogfooding loop will then probe.

**Architecture:** Mirror `apps/tenant-demo` (the cleanest recent app): path-repo composer, `arqel.layout` Inertia root, tracked `storage/framework/*` dirs, `127.0.0.1` Playwright host. Add packages cluster-by-cluster, validating boot + tests after each, so a failure is localized. The showcase MUST be correct (a buggy showcase = false-positive framework bugs).

**Tech Stack:** PHP 8.3+, Laravel 13, Inertia 3, React 19.2, Pest 3, Playwright, SQLite.

**Spec:** [`docs/superpowers/specs/2026-06-05-showcase-dogfood-ecosystem-design.md`](../specs/2026-06-05-showcase-dogfood-ecosystem-design.md)

> **Plan nature (read first):** Because this app spans 20 packages and ~30 files, several tasks say "provide full code, mirroring `apps/tenant-demo`" rather than inlining every migration/model/Resource verbatim. The implementer MUST: (1) use `apps/tenant-demo` as the literal structural template for skeleton/provider/middleware/app.tsx/playwright/E2E (copy + adapt), (2) use the confirmed API reference in this plan for each package's classes/methods, and (3) **validate after every task** (boot + `php artisan test` + the relevant smoke) so a wrong guess fails fast and locally. When a package's real API differs from the reference, read the package `src` and use the real API — adapt, never guess. The acceptance gate for the whole phase is: showcase boots, Pest green, typecheck green, E2E green — a buggy showcase produces false-positive framework bugs, so correctness here is mandatory.

---

## Conventions

- **Branch:** `feat/showcase-dogfood-ecosystem` (already created).
- **Commits:** Conventional + DCO (`git commit --signoff`). Scope `demo` for the app (allowed; `showcase` is not in the commitlint whitelist — use `demo`). Reference the spec.
- **Run app tests:** `cd /home/diogo/PhpstormProjects/arqel/apps/showcase && php artisan test`
- **Lint PHP:** `/home/diogo/PhpstormProjects/arqel/packages/versioning/vendor/bin/pint <files>` (from repo root).
- **No real-money spend:** AI uses `Arqel\Ai\Tests\Fixtures\FakeProvider` (deterministic, $0). Realtime degrades gracefully with no Echo server (log driver). No Stripe/cloud.
- **Never `git add -A`** — stage specific paths. Let the pre-commit hook run.
- **storage/framework gotcha:** after copying the skeleton, `git add -f apps/showcase/storage/framework/{cache/data,sessions,testing,views}/.gitignore` so the dirs exist on CI checkout (else Laravel 500s compiling Blade → E2E webServer timeout).
- **Playwright host:** `127.0.0.1` for host+url+baseURL and `php artisan serve --host=127.0.0.1` (localhost→IPv6 times out in CI).

---

## API reference (confirmed against src)

- **fields/fields-advanced:** `FieldFactory::text/select/...`, `new TextField('x')`, advanced via `FieldFactory::richText/markdown/code` + `RepeaterField::make()`, `BuilderField::make()`, `KeyValueField::make()`, `TagsField::make()`, `WizardField::make()` (namespace `Arqel\FieldsAdvanced\Types`). `Step::make()` / `Block` for wizard/builder.
- **form/table/actions:** `Form::make()->columns()->model()->schema([Section::make()...])`; `(new Table)->columns([TextColumn::make()...])->filters([SelectFilter::make()...])->actions([Actions::edit(), Actions::delete()])->bulkActions([Actions::deleteBulk()])`.
- **widgets:** `Dashboard::make('id','Label','/path')->columns()->widgets([StatWidget::make()->value(fn()=>...), ChartWidget::make()->chartType(ChartWidget::CHART_BAR)->chartData([...]), TableWidget::make()->query(fn()=>...)])`; register via `app(DashboardRegistry::class)->register($dashboard)`.
- **nav:** `NavigationGroup::make('label')->icon()->items([NavigationItem::resource(X::class)])`.
- **tenant:** `BelongsToTenant` trait; config `arqel.tenancy.{resolver,model,identifier_column,relation,foreign_key,switch_column}` + `arqel.middleware` + native `tenant` Inertia prop (the prior sprint made all this config-driven).
- **export:** `ExportAction::make('export')->format(ExportFormat::CSV)` in a Resource's bulk actions.
- **audit:** `use Arqel\Audit\Concerns\LogsActivity;` on a Model (hard-requires `spatie/laravel-activitylog`).
- **versioning:** `use Arqel\Versioning\Concerns\Versionable;` on a Model.
- **workflow:** `use Arqel\Workflow\Concerns\HasWorkflow;` + `public function arqelWorkflow(): WorkflowDefinition { return WorkflowDefinition::make('status')->states([...])->transitions([...]); }`.
- **realtime:** `use Arqel\Realtime\Concerns\BroadcastsResourceUpdates;` on a Resource; broadcasting default `log` (no server, degrades gracefully).
- **ai:** `(new AiTextField('summary'))->prompt('...')->provider('test')->contextFields([...])`; config `arqel-ai.default_provider = 'test'` + `providers.test.driver = FakeProvider::class`.
- **mcp:** auto-discovered via `McpServiceProvider`; smoke-only (assert `McpServer` resolves).

---

## File Structure (high level)

```
apps/showcase/
├── (skeleton copied from apps/tenant-demo, demo code stripped)
├── composer.json            # path repos + require for ALL 20 packages + spatie/laravel-activitylog
├── package.json             # name @arqel-dev/showcase
├── config/arqel.php         # panel + tenancy + middleware + resources.discover
├── config/arqel-ai.php      # FakeProvider stub
├── config/broadcasting.php  # default 'log'
├── app/Models/{User,Tenant,Author,Post,Category,Comment,Ticket,Setting}.php
├── app/Arqel/Resources/{PostResource,AuthorResource,TicketResource,SettingResource}.php
├── app/Arqel/Dashboards/MainDashboard.php
├── app/Providers/ArqelServiceProvider.php
├── app/Http/Middleware/HandleInertiaRequests.php
├── database/migrations/* + factories/* + seeders/DatabaseSeeder.php
├── resources/js/app.tsx
├── tests/Feature/* (smoke per cluster)
└── tests/e2e/* (Playwright)
```

---

## Task 1: Scaffold the skeleton from tenant-demo

**Files:** `apps/showcase/` tree.

- [ ] **Step 1: Copy + strip.**
```bash
cd /home/diogo/PhpstormProjects/arqel
rm -rf apps/showcase
cp -r apps/tenant-demo apps/showcase
rm -rf apps/showcase/vendor apps/showcase/node_modules apps/showcase/public/build
rm -f apps/showcase/composer.lock apps/showcase/pnpm-lock.yaml apps/showcase/.phpunit.result.cache apps/showcase/.env
rm -rf apps/showcase/test-results apps/showcase/playwright-report
rm -f apps/showcase/storage/logs/*.log apps/showcase/database/database.sqlite
# strip tenant-demo business code (we rebuild richer below)
rm -f apps/showcase/app/Models/{Project,Tenant}.php
rm -f apps/showcase/app/Arqel/Resources/ProjectResource.php
rm -f apps/showcase/database/factories/{ProjectFactory,TenantFactory}.php
rm -f apps/showcase/database/migrations/2026_05_08_*.php
rm -rf apps/showcase/tests/e2e apps/showcase/tests/Feature/*.php
rm -rf apps/showcase/bootstrap/cache/services.php apps/showcase/bootstrap/cache/packages.php
```

- [ ] **Step 2: Rename in composer.json + package.json.** Set `composer.json` `name` to `arqel-dev/showcase`, description "Comprehensive showcase app exercising every Arqel package."; set `package.json` `name` to `@arqel-dev/showcase`.

- [ ] **Step 3: Validate JSON + commit the bare skeleton.**
```bash
python3 -c "import json; json.load(open('apps/showcase/composer.json')); json.load(open('apps/showcase/package.json'))"
git -C /home/diogo/PhpstormProjects/arqel add -f apps/showcase/storage/framework/cache/data/.gitignore apps/showcase/storage/framework/sessions/.gitignore apps/showcase/storage/framework/testing/.gitignore apps/showcase/storage/framework/views/.gitignore
git -C /home/diogo/PhpstormProjects/arqel add apps/showcase
git -C /home/diogo/PhpstormProjects/arqel commit --signoff -m "chore(demo): scaffold apps/showcase skeleton from tenant-demo"
```

(Note: this task is mechanical scaffolding; the rich content lands in later tasks. The commit is a checkpoint, not a working app yet.)

---

## Task 2: Wire all 20 packages into composer.json

**Files:** `apps/showcase/composer.json`

- [ ] **Step 1: Add path repos + require for every package.** Read the current `composer.json`, then ensure `repositories` has a `{ "type": "path", "url": "../../packages/<pkg>", "options": { "symlink": true } }` entry for: arqel, core, auth, fields, form, actions, nav, table, widgets, tenant, fields-advanced, export, audit, versioning, workflow, realtime, ai, mcp. And `require` has `"arqel-dev/<pkg>": "^0.12.0"` for framework + tenant + fields-advanced + widgets + export + audit + versioning + workflow + realtime + ai + mcp + nav, plus `"spatie/laravel-activitylog": "^4.10"`.

- [ ] **Step 2: composer install.**
```bash
cd /home/diogo/PhpstormProjects/arqel/apps/showcase
composer install --no-interaction --prefer-dist 2>&1 | tail -15
```
Expected: clean resolution (all path repos symlink). If a package's own `require` pulls an unmet dep (e.g. export needs spatie/simple-excel + dompdf), composer resolves it from packagist — confirm no conflict. If audit's hard `spatie/laravel-activitylog` or workflow's optional `spatie/laravel-model-states` is needed, add it to require and re-install.

- [ ] **Step 3: Smoke the boot.**
```bash
cp .env.example .env && php artisan key:generate && touch database/database.sqlite
php artisan about 2>&1 | grep -iE "environment|error" | head -3
```
Expected: no fatal. If a ServiceProvider from a newly-added package throws at boot, that's a real finding — note it (it may become the FIRST dogfood bug), but for the showcase to be a clean baseline it must boot; investigate and, if it's a genuine framework boot bug, STOP and report it (it should be fixed before the showcase ships).

- [ ] **Step 4: Commit.**
```bash
git -C /home/diogo/PhpstormProjects/arqel add apps/showcase/composer.json apps/showcase/composer.lock
git -C /home/diogo/PhpstormProjects/arqel commit --signoff -m "chore(demo): wire all 20 arqel packages into showcase composer"
```

---

## Task 3: Domain models + migrations + factories

**Files:** `apps/showcase/app/Models/*`, `database/migrations/*`, `database/factories/*`

The showcase needs a small but rich domain: `Author` (1) ──< `Post` (M) >── `Category` (M:M), `Post` ──< `Comment`, `Ticket` (workflow), `Setting` (KeyValue/Repeater fields), plus `Tenant` + tenant scoping on `Post`.

- [ ] **Step 1: Migrations.** Create migrations for: `categories` (id, name, slug, timestamps); `authors` (id, name, email, bio, timestamps); `posts` (id, tenant_id nullable FK, author_id FK, title, slug, body text, status string, featured bool, published_at nullable, meta json, timestamps); `category_post` pivot; `comments` (id, post_id FK, body, timestamps); `tickets` (id, subject, status string default 'open', timestamps); `settings` (id, key, value json, timestamps); `tenants` (id, name, slug, timestamps) + `tenant_user` pivot + `current_tenant_id` on users. (Provide each migration's full `up()`/`down()` — mirror the tenant-demo migrations' style: `Schema::create(... function (Blueprint $t) {...})`.)

- [ ] **Step 2: Models.** Create `Author`, `Category`, `Post`, `Comment`, `Ticket`, `Setting`, `Tenant`, and extend `User`. `Post` uses `BelongsToTenant` (tenant), `LogsActivity` (audit), `Versionable` (versioning); `Ticket` uses `HasWorkflow` with an `arqelWorkflow()` returning `WorkflowDefinition::make('status')->states(['open'=>[...], 'in_progress'=>[...], 'resolved'=>[...]])->transitions([])`. Declare relations (`author()`, `categories()`, `comments()`, etc.) and `$fillable`. (Provide each model's full code.)

- [ ] **Step 3: Factories + seeder.** `AuthorFactory`, `CategoryFactory`, `PostFactory` (with author + categories + tenant_id state), `CommentFactory`, `TicketFactory`, `TenantFactory`. `DatabaseSeeder` creates 2 tenants, ~5 authors, ~10 categories, ~30 posts (attached to categories + comments + tenant), ~8 tickets in varied states, an admin user (admin@arqel.test / password) attached to both tenants with current_tenant_id set. (Provide full code; mirror tenant-demo's idempotent `firstOrCreate` admin pattern.)

- [ ] **Step 4: Migrate + seed + verify.**
```bash
cd /home/diogo/PhpstormProjects/arqel/apps/showcase
php artisan migrate:fresh --seed --force 2>&1 | tail -15
php artisan tinker --execute="echo 'posts='.App\Models\Post::withoutGlobalScopes()->count().' tickets='.App\Models\Ticket::count();"
```
Expected: migrations run, seed populates (posts > 0).

- [ ] **Step 5: Lint + commit.** Pint the new PHP, then commit `app/Models`, `database/migrations`, `database/factories`, `database/seeders` with `feat(demo): showcase domain models + migrations + factories`.

---

## Task 4: Resources — core/fields/fields-advanced/form/table/actions/export

**Files:** `apps/showcase/app/Arqel/Resources/{PostResource,AuthorResource,TicketResource,SettingResource}.php`

- [ ] **Step 1: PostResource.** A rich Resource exercising the widest field surface. `fields()`/`form()` (layout-aware with `Section::make('Content')` + `Section::make('Meta')`) using: `TextField('title')->required()`, `Field::slug('slug')->fromField('title')`, `FieldFactory::richText('body')` (fields-advanced), `Field::select('status')->options([...])`, `BooleanField('featured')`, `DateTimeField('published_at')`, `FieldFactory::keyValue('meta')` or `KeyValueField::make('meta')`, a `belongsTo` author field, a `multiSelect`/relationship categories field. `table()` with `TextColumn::make('title')->sortable()->searchable()`, `BadgeColumn::make('status')->colors([...])`, `BooleanColumn::make('featured')`, `DateColumn::make('published_at')->sortable()`, `SelectFilter::make('status')`, `TernaryFilter::make('featured')`, `->searchable()->selectable()->defaultSort('published_at','desc')->actions([Actions::edit(), Actions::delete()])->bulkActions([Actions::deleteBulk(), ExportAction::make('export')->format(ExportFormat::CSV)])`. Keep `fields()` and `form()` in sync (effectiveFields unifies validation now, but keep both for the index fallback).

- [ ] **Step 2: AuthorResource.** Simpler Resource: text/email/textarea fields, a `hasMany` posts indication, table with name/email columns + search.

- [ ] **Step 3: TicketResource.** Exercises workflow: status as a `BadgeColumn` with state colors; the form shows current state + available transitions (read `arqelWorkflow()`); fields `subject` (text) + `status` (select from workflow states).

- [ ] **Step 4: SettingResource.** Exercises advanced fields: `RepeaterField::make('items')->schema([TextField::make('label'), TextField::make('value')])`, `TagsField::make('tags')`, `CodeField::make('snippet')->language('json')`, `MarkdownField::make('notes')`. (Use a single Setting row or a small table.)

- [ ] **Step 5: Register + boot-check each Resource.** Add all four to the panel in `ArqelServiceProvider` (Task 6 wires the provider; for now, after that task, verify each Resource's `/admin/{slug}` index builds without throwing — done in Task 8's smoke tests). For this task, just lint + commit the Resources with `feat(demo): showcase Resources (fields, advanced fields, table, actions, export)`.

(If any field/column/action class name differs from the API reference when you write it, read the package src and use the real one; adapt rather than guess.)

---

## Task 5: Dashboard + widgets + navigation

**Files:** `apps/showcase/app/Arqel/Dashboards/MainDashboard.php`

- [ ] **Step 1: MainDashboard.** `Dashboard::make('main', 'Overview', '/admin')->columns(['sm'=>1,'md'=>2,'lg'=>3])->widgets([ StatWidget::make('total_posts')->heading('Posts')->value(fn()=>Post::withoutGlobalScopes()->count())->icon('file-text'), StatWidget::make('total_authors')->heading('Authors')->value(fn()=>Author::count()), ChartWidget::make('posts_by_status')->chartType(ChartWidget::CHART_BAR)->chartData(fn()=>[...derive from Post grouped by status...]), TableWidget::make('recent_posts')->query(fn()=>Post::withoutGlobalScopes()->latest()->limit(5))->columns([...])->seeAllUrl('/admin/posts') ])`. (Provide full code; derive chartData/columns concretely.)

- [ ] **Step 2: Navigation.** In the provider (Task 6), declare nav groups (`Content` → Posts/Authors/Categories; `Support` → Tickets; `System` → Settings) via the panel's navigation API, or rely on auto-registration from `->resources([...])` if that's the established pattern (check apps/demo). Use `NavigationGroup::make()`/`NavigationItem::resource()` if explicit grouping is wanted.

- [ ] **Step 3: Lint + commit** `feat(demo): showcase dashboard + widgets + navigation`.

(The dashboard renders via the widgets package's `/admin` route + `DashboardController`; registration in Task 6.)

---

## Task 6: ArqelServiceProvider + config + middleware + bootstrap + app.tsx

**Files:** `apps/showcase/app/Providers/ArqelServiceProvider.php`, `config/arqel.php`, `config/arqel-ai.php`, `config/broadcasting.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `bootstrap/app.php`, `resources/js/app.tsx`

- [ ] **Step 1: config/arqel.php.** Panel `path` admin; `tenancy` block (enabled, resolver AuthUserResolver, model Tenant, identifier_column slug, relation currentTenant, foreign_key tenant_id, switch_column current_tenant_id); top-level `middleware => ['web','auth','arqel.tenant:optional']`; `resources.discover => false` (we register explicitly). (Mirror tenant-demo's config exactly, extended.)

- [ ] **Step 2: config/arqel-ai.php.** `default_provider => 'test'`, `providers.test.driver => Arqel\Ai\Tests\Fixtures\FakeProvider::class` (cost 0). Confirm `FakeProvider` is autoloadable from the app (it lives in the ai package's tests namespace — if it is NOT autoloaded in non-test context, create a tiny app-local `App\Ai\StubProvider implements Arqel\Ai\Contracts\AiProvider` returning deterministic strings instead). Verify by reading `packages/ai/src/Contracts/AiProvider.php` for the exact interface methods to implement.

- [ ] **Step 3: broadcasting.** Set `config/broadcasting.php` default to `'log'` (no real server; realtime events log instead of broadcasting — degrades gracefully, no spend).

- [ ] **Step 4: ArqelServiceProvider.** Mirror tenant-demo's provider: push HandleInertiaRequests to web group, `Inertia::setRootView(config('arqel.inertia.root_view'))`, bind the AuthUserResolver if needed (config-driven now, so likely just config), declare the panel `->panel('admin')->path('admin')->brand()->login()->afterLoginRedirectTo('/admin')->resources([PostResource, AuthorResource, TicketResource, SettingResource])`, `setCurrent('admin')`, register `Arqel\Auth\Routes`, and register the MainDashboard in `DashboardRegistry`. Share the `tenant` prop natively (no workaround needed — the core does it).

- [ ] **Step 5: HandleInertiaRequests.** Mirror tenant-demo (auth + app shared props; tenant prop is native from core now).

- [ ] **Step 6: app.tsx.** Mirror tenant-demo's app.tsx: `createArqelApp` with `arqelPages` wrapped in an admin layout (AppShell + Sidebar + Topbar), `TenantSwitcher` in the Topbar reading `props.tenant`, auth pages, register `@arqel-dev/fields/register` AND `@arqel-dev/fields-advanced/register` (if it exposes a register entry — check `packages-js/fields-advanced/package.json` exports), and any widgets register entry.

- [ ] **Step 7: Boot + migrate + frontend build + smoke.**
```bash
cd /home/diogo/PhpstormProjects/arqel/apps/showcase
php artisan config:clear && php artisan migrate:fresh --seed --force >/dev/null 2>&1
cd /home/diogo/PhpstormProjects/arqel && pnpm install && pnpm --filter @arqel-dev/showcase build 2>&1 | tail -5
pkill -9 -f "artisan serve" 2>/dev/null || true
cd apps/showcase && php artisan serve --host=127.0.0.1 --port=8002 >/tmp/showcase.log 2>&1 &
sleep 4
curl -s -o /dev/null -w 'login=%{http_code}\n' http://127.0.0.1:8002/admin/login
pkill -9 -f "artisan serve" 2>/dev/null || true
```
Expected: frontend builds, `/admin/login` = 200. If 500, read `/tmp/showcase.log` + `storage/logs/laravel.log` and fix the wiring.

- [ ] **Step 8: Lint + commit** `feat(demo): showcase provider, config, middleware, frontend wiring`.

---

## Task 7: Feature tests (smoke per cluster) — proves the showcase is correct

**Files:** `apps/showcase/tests/Feature/ShowcaseSmokeTest.php` (+ the bundled `TestCase`)

- [ ] **Step 1: Smoke tests.** Write feature tests (authenticated via `actingAs` an admin) that GET each Resource index + create page and assert 200 + an Inertia component, exercise: a Post store (validation + create), a workflow transition on a Ticket, the dashboard `/admin` renders, the tenant prop is shared, an export action runs (dry-run if the action supports it, else assert it's registered). These tests are the showcase's own correctness gate — they must pass so the dogfood loop starts from green. (Provide concrete test bodies; reuse tenant-demo's `TenantShareTest` patterns + the core `InertiaValidationFlowTest` patterns.)

- [ ] **Step 2: Run + green.**
```bash
cd /home/diogo/PhpstormProjects/arqel/apps/showcase && php artisan test 2>&1 | tail -10
```
Expected: green. Any failure here is either a showcase wiring bug (fix it) OR a genuine framework bug (if so, STOP and report — the baseline must be green before the loop).

- [ ] **Step 3: Commit** `test(demo): showcase smoke tests (per-cluster correctness gate)`.

---

## Task 8: E2E (Playwright) — visual + functional flows

**Files:** `apps/showcase/playwright.config.ts`, `tests/e2e/{setup,fixtures,01-resources,02-dashboard,03-tenant,04-advanced-fields}.spec.ts`

- [ ] **Step 1: Config + fixtures.** `playwright.config.ts` on port 8002, host `127.0.0.1` (host+url+baseURL + `php artisan serve --host=127.0.0.1 --port=8002`), `reuseExistingServer: !CI`. `setup.ts` runs `migrate:fresh --seed --force`. `fixtures.ts` `loggedInPage` (login admin@arqel.test / password). Mirror tenant-demo exactly.

- [ ] **Step 2: Specs.** (a) `01-resources`: list shows posts, filter by status, sort, search, open create, validation error on empty required, create succeeds, row edit, row delete, bulk select + delete. (b) `02-dashboard`: `/admin` renders the dashboard with the stat widgets + chart + recent-posts table. (c) `03-tenant`: switch tenant, list re-scopes. (d) `04-advanced-fields`: open the Setting/Post create page and assert the rich-text / repeater / tags / code field components render (by `data-testid` or role). Use stable selectors. (Provide concrete spec bodies adapted from tenant-demo's `01-tenant-switching`.)

- [ ] **Step 3: Build + run E2E.**
```bash
cd /home/diogo/PhpstormProjects/arqel
pnpm --filter @arqel-dev/showcase build
pnpm --filter @arqel-dev/showcase exec playwright install chromium
pkill -9 -f "artisan serve" 2>/dev/null || true
pnpm --filter @arqel-dev/showcase test:e2e 2>&1 | tail -20
```
Expected: all specs pass. Debug failures (selector / scope / boot). If a failure is a genuine framework bug, note it — but the baseline E2E must be green to ship Phase 0.

- [ ] **Step 4: Commit** `test(demo): showcase E2E specs (resources, dashboard, tenant, advanced fields)`.

---

## Task 9: CI integration + README

**Files:** `.github/workflows/ci.yml`, `apps/showcase/README.md`

- [ ] **Step 1: Extend the e2e job.** After the tenant-demo steps in `.github/workflows/ci.yml`, append analogous steps for `apps/showcase`: composer install, `.env`+key+sqlite, `migrate:fresh --seed --force`, the smoke-check (boot 127.0.0.1:8002, poll `/admin/login` for 200, print log on failure, then stop), run E2E, upload report on failure. Mirror the tenant-demo block exactly (with port 8002 + showcase paths). `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/ci.yml'))"` to validate.

- [ ] **Step 2: README.** A short `apps/showcase/README.md` listing which packages it exercises and how to run it (composer install, migrate:fresh --seed, build, serve --host=127.0.0.1 --port=8002, login).

- [ ] **Step 3: Commit** `ci(demo): run showcase E2E in CI + README`.

---

## Task 10: Final validation + coverage report

**Files:** `docs/superpowers/reports/2026-06-05-showcase-coverage.md`

- [ ] **Step 1: Full local validation.**
```bash
cd /home/diogo/PhpstormProjects/arqel/apps/showcase && php artisan migrate:fresh --seed --force && php artisan test
cd /home/diogo/PhpstormProjects/arqel && pnpm --filter @arqel-dev/showcase build && pkill -9 -f "artisan serve" 2>/dev/null || true; pnpm --filter @arqel-dev/showcase test:e2e
```
Expected: tests + E2E green.

- [ ] **Step 2: Coverage report.** Write `docs/superpowers/reports/2026-06-05-showcase-coverage.md`: a table of all 20 packages × how the showcase exercises each (or "out-of-scope: <reason>" for any genuinely impossible without spend — e.g. marketplace/Stripe, real AI provider). This report is the input the dogfood loop's detection agents use to know what to probe.

- [ ] **Step 3: Commit** `docs(docs): showcase package coverage report`.

- [ ] **Step 4: Proceed to finishing-a-development-branch** → push + PR + merge on CLEAN CI. This is the Phase-0 baseline; the autonomous loop (separate plan) runs after it's on `main`.
