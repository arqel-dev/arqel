# Batch #3 — Prompts prontos para deploy

> Despachar SOMENTE quando Batch #2 (A2/B2/C2) estiver 100% mergeado em main.
> Os 3 alvos tocam packages diferentes (tenant / export-novo / audit-novo) — zero conflito entre si.
> Cada agente roda em worktree isolado com `isolation: "worktree"` + `run_in_background: true`.

---

## Regras compartilhadas (incluir em TODOS os 3 prompts)

- **NÃO TOCAR** em ficheiros root partilhados:
  - `composer.json` (root), `commitlint.config.mjs`, `pnpm-workspace.yaml`, `phpstan.neon`, `CLAUDE.md`, `docs/tickets/current.md`, `CHANGELOG.md`. O orquestrador consolida pós-merge.
- Stack: PHP 8.3+, Laravel 12|13, Pest 3, PHPStan max, `declare(strict_types=1)` em todos os ficheiros PHP, classes `final` por convenção exceto bases abstratas.
- Spatie\LaravelPackageTools\PackageServiceProvider pattern (ver `packages/widgets/src/WidgetsServiceProvider.php` e `packages/tenant/src/TenantServiceProvider.php` como referência).
- SKILL.md PT-BR seguindo formato canónico de `PLANNING/00-index.md` §5 (Purpose / Status / Conventions / Anti-patterns / Related).
- Validação obrigatória antes de commit: `vendor/bin/pest packages/<pkg>` 100% green + `vendor/bin/phpstan analyse packages/<pkg>` clean + `vendor/bin/pint --test packages/<pkg>` clean.
- Commit Conventional + DCO sign-off, body referencia o ticket. Único commit por ticket.
- Reportar no resultado: ficheiros criados/modificados, contagem de testes (delta), validações executadas, decisões não-óbvias.

---

## Cluster A3 — TENANT-009 (Tenant switcher backend)

**Subagent type:** `general-purpose`
**Description:** "Cluster A3: TENANT-009 Tenant switcher backend"
**Worktree:** isolation `worktree`
**Background:** true

### Prompt

You are working on the Arqel monorepo (Laravel admin panel framework). Your task is **TENANT-009 — Tenant switcher backend** in `packages/tenant/`.

**Read first (in this order):**
1. `PLANNING/09-fase-2-essenciais.md` lines 677–755 (TENANT-009 spec)
2. `packages/tenant/SKILL.md` (current state of the package)
3. `packages/tenant/src/TenantManager.php` and `packages/tenant/src/TenantServiceProvider.php` (existing API)
4. `packages/tenant/src/Resolvers/AuthUserResolver.php` (default resolver)

**Implement:**

1. `packages/tenant/src/Events/TenantSwitched.php` — final class with readonly properties `from: ?Tenant`, `to: Tenant`, `user: Authenticatable`. (`Tenant` is the existing model/contract in the tenant package — confirm by reading the source.)
2. `packages/tenant/src/Http/Controllers/TenantSwitcherController.php` — final, two methods:
   - `switch(Request, TenantManager, string $tenantId): RedirectResponse` — resolves tenant for user, aborts 404 if not found, 403 if not authorized, applies switch via `TenantManager` (delegate to a manager method or to the resolver — pick the cleanest path that fits existing API), dispatches `TenantSwitched`, redirects to `/admin` (use `redirect()->intended('/admin')`).
   - `list(Request): JsonResponse` — returns `{ current: {...}, available: [...] }` with `id/name/slug/logo` projection only.
3. Routes: register in a new `packages/tenant/routes/admin.php` (or extend whatever the package already does — check `TenantServiceProvider` for existing route loading; mirror that). Routes:
   - `POST /admin/tenants/{tenantId}/switch` → `TenantSwitcherController@switch` (named `arqel.tenant.switch`)
   - `GET /admin/tenants/available` → `TenantSwitcherController@list` (named `arqel.tenant.available`)
   - Use middleware `['web', 'auth']` (or whatever the existing tenant middleware stack is — match it).
4. `TenantManager`: add `availableFor(Authenticatable $user): array` and `canSwitchTo(Authenticatable, Tenant): bool` if not already there. Delegate to the active resolver (extend the resolver contract if needed — see `Resolvers/Resolver.php` or equivalent contract; if extending, default-implement on existing concrete resolvers to keep BC).
5. Tests in `packages/tenant/tests/Feature/TenantSwitcherTest.php`:
   - happy path switch (200 redirect, event fired, manager.current changed)
   - unauthorized switch → 403
   - unknown tenant → 404
   - `list` endpoint returns only authorized tenants
   - Use `Event::fake()` to assert event dispatch.

**Constraints:**
- DO NOT modify root configs (composer.json root, commitlint, CLAUDE.md, etc.) — orchestrator handles consolidation.
- Update `packages/tenant/SKILL.md` adding TENANT-009 to "Entregue" section with brief detail block (mirror existing entry style).
- All Pest tests must pass: `vendor/bin/pest packages/tenant`.
- PHPStan clean: `vendor/bin/phpstan analyse packages/tenant`.
- Pint clean: `vendor/bin/pint --test packages/tenant`.

**Commit format:**
```
feat(tenant): tenant switcher backend (TENANT-009)

Implements TENANT-009 from PLANNING/09-fase-2-essenciais.md.

- TenantSwitcherController with switch/list endpoints
- TenantSwitched event
- routes registered under /admin/tenants
- TenantManager::availableFor / canSwitchTo

Signed-off-by: ...
```

**Report back:** files created/modified, test count delta, validation outcomes, any non-obvious decisions (e.g. resolver contract changes, route loading approach).

---

## Cluster B3 — EXPORT-001 (Scaffold `arqel-dev/export`)

**Subagent type:** `general-purpose`
**Description:** "Cluster B3: EXPORT-001 Scaffold arqel-dev/export"
**Worktree:** isolation `worktree`
**Background:** true

### Prompt

You are working on the Arqel monorepo. Your task is **EXPORT-001 — Esqueleto do pacote `arqel-dev/export`** (PLANNING/09-fase-2-essenciais.md lines 4391–4423).

**Read first:**
1. PLANNING/09-fase-2-essenciais.md lines 4391–4500 (EXPORT-001 + first dependent ticket for context)
2. `packages/widgets/composer.json` and `packages/widgets/src/WidgetsServiceProvider.php` (mirror this scaffold pattern)
3. `packages/mcp/SKILL.md` (recent scaffold-only skill template — mirror its structure)
4. `packages/actions/src/BulkAction.php` (the BulkAction base your `ExportAction` will extend)

**Create the package `packages/export/`:**

1. `composer.json`:
   - `"name": "arqel-dev/export"`, type library, MIT, PSR-4 `Arqel\Export\` → `src/`, autoload-dev `Arqel\Export\Tests\` → `tests/`.
   - require: `php ^8.3`, `illuminate/support ^12.0|^13.0`, `arqel-dev/core: @dev`, `arqel-dev/actions: @dev`, `spatie/laravel-package-tools ^1.16`.
   - suggest: `spatie/simple-excel` (CSV/XLSX), `dompdf/dompdf` (PDF). NO hard dep on these — exporters are stubs in this ticket.
   - require-dev: `pestphp/pest`, `pestphp/pest-plugin-laravel`, `orchestra/testbench`, `larastan/larastan`, `laravel/pint`.
   - `extra.laravel.providers: ["Arqel\\Export\\ExportServiceProvider"]`.
2. `src/ExportFormat.php` — backed enum `string`: `CSV='csv'`, `XLSX='xlsx'`, `PDF='pdf'`. Add `mimeType(): string` and `extension(): string` helpers.
3. `src/Contracts/Exporter.php` — interface `export(iterable $rows, array $columns, string $destination): string` (returns the path written) — keep it simple.
4. `src/Exporters/CsvExporter.php`, `XlsxExporter.php`, `PdfExporter.php` — implement the contract as STUBS that throw `RuntimeException("Not implemented in EXPORT-001 — see EXPORT-002/003/004")`. Each is `final`.
5. `src/Actions/ExportAction.php` — final, extends `Arqel\Actions\BulkAction`. Factory `make(string $name = 'export')`. Setter `format(ExportFormat)`. Default label `'Export'`, icon `'download'`. Execution body throws `RuntimeException("Wired in EXPORT-005")` for now (consistent stub posture).
6. `src/ExportServiceProvider.php` — extends `Spatie\LaravelPackageTools\PackageServiceProvider`. `configurePackage` sets name `'arqel-export'`. No migrations / no config in this ticket (EXPORT-001 is scaffold only — Export model + migration come in a later ticket; document that in the SKILL.md).
7. `SKILL.md` PT-BR — Purpose / Status (apenas EXPORT-001 entregue, lista o que falta) / Conventions / Anti-patterns / Related.
8. Tests in `tests/`:
   - `Pest.php`, `TestCase.php` (extends `Orchestra\Testbench\TestCase`, registers `ExportServiceProvider`).
   - `Unit/ExportFormatTest.php` — enum cases + `mimeType`/`extension`.
   - `Feature/ExportServiceProviderTest.php` — provider boots, package registered.
   - `Unit/ExportActionTest.php` — `make()` returns instance with default name/label/icon, `format()` is fluent.
   - `Unit/ExportersTest.php` — instantiation works, `export()` throws `RuntimeException` for each of the 3 stubs.

**Constraints:**
- DO NOT modify root configs or other packages.
- All Pest tests must pass: `vendor/bin/pest packages/export`.
- PHPStan clean: `vendor/bin/phpstan analyse packages/export`.
- Pint clean: `vendor/bin/pint --test packages/export`.
- Run `composer dump-autoload` only inside the package if needed; do NOT touch root composer.

**Commit format:**
```
feat(export): scaffold arqel-dev/export package (EXPORT-001)

Implements EXPORT-001 from PLANNING/09-fase-2-essenciais.md.

- ExportFormat enum (CSV/XLSX/PDF)
- Exporter contract + 3 stub implementations
- ExportAction (BulkAction) stub
- ExportServiceProvider (auto-discovered)
- SKILL.md + tests

Signed-off-by: ...
```

**Report back:** all created files, test count, validation outcomes, decisions (e.g. why no `Export` model yet — because spec separates that into a later ticket).

---

## Cluster C3 — AUDIT-001 (Scaffold `arqel-dev/audit`)

**Subagent type:** `general-purpose`
**Description:** "Cluster C3: AUDIT-001 Scaffold arqel-dev/audit"
**Worktree:** isolation `worktree`
**Background:** true

### Prompt

You are working on the Arqel monorepo. Your task is **AUDIT-001 — Pacote `arqel-dev/audit` wraps spatie/laravel-activitylog** (PLANNING/09-fase-2-essenciais.md lines 5000–5057).

**Read first:**
1. PLANNING/09-fase-2-essenciais.md lines 5000–5070 (AUDIT-001 spec)
2. `packages/widgets/composer.json` and `packages/widgets/src/WidgetsServiceProvider.php` (scaffold reference)
3. `packages/tenant/SKILL.md` (skill template style)

**Create the package `packages/audit/`:**

1. `composer.json`:
   - `"name": "arqel-dev/audit"`, MIT, PSR-4 `Arqel\Audit\` → `src/`, autoload-dev `Arqel\Audit\Tests\` → `tests/`.
   - require: `php ^8.3`, `illuminate/support ^12.0|^13.0`, `spatie/laravel-activitylog ^4.10`, `spatie/laravel-package-tools ^1.16`. **This package DOES have a hard dep on the Spatie lib** — that is the wrapper purpose, not optional.
   - require-dev: `pestphp/pest`, `pestphp/pest-plugin-laravel`, `orchestra/testbench`, `larastan/larastan`, `laravel/pint`.
   - `extra.laravel.providers: ["Arqel\\Audit\\AuditServiceProvider"]`.
2. `src/Concerns/LogsActivity.php` — trait that uses `Spatie\Activitylog\Traits\LogsActivity as SpatieLogsActivity` with alias `_getSpatieOptions`. Implements:
   - `getActivitylogOptions(): LogOptions` returning `LogOptions::defaults()->logOnly($this->getAuditableAttributes())->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName(class_basename($this))`.
   - `getAuditableAttributes(): array` defaulting to `$this->fillable ?? ['*']`.
   - Mark `getAuditableAttributes` as `protected` so subclasses override.
3. `src/Http/Controllers/ActivityLogController.php` — final, single `index(Request)` method returning `Inertia::render('arqel::activity-log', [...])` paginated activities (use `Spatie\Activitylog\Models\Activity::query()->latest()->paginate(50)`). Map each entry to a serialisable shape (id, log_name, description, subject_type, subject_id, causer_type, causer_id, properties, created_at). For AUDIT-001 (scaffold) keep it minimal — full filtering / UI come later.
4. `src/AuditServiceProvider.php` — extends `Spatie\LaravelPackageTools\PackageServiceProvider`, name `'arqel-audit'`. No routes registered yet in scaffold (document deferral in SKILL.md). Just boots cleanly.
5. `SKILL.md` PT-BR — Purpose / Status / Conventions (mention "trait `LogsActivity` é o entry point") / Anti-patterns (e.g. ❌ não logar attributes sensíveis sem override `getAuditableAttributes`) / Related.
6. Tests in `tests/`:
   - `Pest.php`, `TestCase.php` extending `Orchestra\Testbench\TestCase`, registers `AuditServiceProvider` AND `ActivitylogServiceProvider`. Run Spatie's migrations in `setUp()` (`$this->loadMigrationsFrom(__DIR__.'/../vendor/spatie/laravel-activitylog/database/migrations')` or use `$this->artisan('migrate', ...)` — match what works with testbench).
   - `Feature/AuditServiceProviderTest.php` — provider boots, package registered.
   - `Feature/LogsActivityTraitTest.php` — fixture model `FakeAuditableModel` (use eloquent migration in test or schema setup), use trait, perform create/update/delete, assert `Activity` rows exist with correct `log_name`, `description`, `properties` (old/new attributes for update).
   - `Unit/LogsActivityOptionsTest.php` — instantiate fixture, call `getActivitylogOptions()`, assert config (logOnlyDirty true, log name = class basename).
   - `Unit/ActivityLogControllerTest.php` (light) — controller exists, `index` is callable (skip Inertia render assertions if too heavy in scaffold).

**Constraints:**
- DO NOT modify root configs or other packages.
- All Pest tests must pass: `vendor/bin/pest packages/audit`.
- PHPStan clean: `vendor/bin/phpstan analyse packages/audit`.
- Pint clean: `vendor/bin/pint --test packages/audit`.

**Commit format:**
```
feat(audit): scaffold arqel-dev/audit package (AUDIT-001)

Implements AUDIT-001 from PLANNING/09-fase-2-essenciais.md.

- LogsActivity trait wraps Spatie defaults (logOnlyDirty, class basename log name)
- ActivityLogController stub
- AuditServiceProvider (auto-discovered)
- Hard dep on spatie/laravel-activitylog ^4.10
- SKILL.md + tests

Signed-off-by: ...
```

**Report back:** files created, test count, validation outcomes, decisions (especially: how testbench migrations were wired for the Activity table).

---

## Pós-deploy (orquestrador)

Quando os 3 agentes reportarem:
1. Sequential merge para main (resolver conflitos em SKILL.md ou docs se houver — não devem haver entre packages diferentes).
2. Atualizar root `composer.json` adicionando `"arqel-dev/export": "@dev"` e `"arqel-dev/audit": "@dev"` em require-dev.
3. Atualizar `commitlint.config.mjs` adicionando scopes `'export'` e `'audit'`.
4. Validação global: `vendor/bin/pest` (suite completa) + `vendor/bin/phpstan analyse` global.
5. Atualizar `docs/tickets/current.md` + `CHANGELOG.md` com entrada do Batch #3.
6. `git worktree remove -f` + `git branch -D` para os 3 worktrees.
