# Showcase package coverage report — mapa de detecção do dogfood loop

**Data:** 2026-06-05
**App:** `apps/showcase` (`@arqel-dev/showcase`)
**Branch:** `feat/showcase-dogfood-ecosystem`
**Spec/plano:** apps/showcase Phase 0 (Tasks 1–10)

---

## O que é o showcase

`apps/showcase` é o app de **dogfooding** do Arqel: um único painel admin Laravel
que exercita **todos os 20 pacotes** do ecossistema num só lugar, validando o
framework end-to-end com algo real em vez de fixtures unitárias. Ele entrega quatro
Resources (`Post`, `Author`, `Ticket`, `Setting`), um dashboard de widgets,
multi-tenancy, audit logging, versioning, uma máquina de estados de workflow, um
provider de AI offline e um servidor MCP — tudo conectado num painel `admin`.

Este app é o **baseline known-good** sobre o qual o loop autônomo de dogfood roda:
quando o loop encontra divergência entre o comportamento observado e o esperado
aqui, ela é candidata a bug de framework. Este relatório é o **mapa de detecção**
do loop — mapeia cada pacote ao ponto exato de fiação no showcase, com dicas de
sondagem (probe hints).

## Status de validação (2026-06-05)

| Gate | Comando | Resultado |
|---|---|---|
| Feature/Pest | `php artisan test` | **8 passed, 91 assertions** ✅ |
| Build frontend | `pnpm --filter @arqel-dev/showcase build` | ✅ (built in 3.79s) |
| E2E Playwright | `pnpm --filter @arqel-dev/showcase test:e2e` | **12 passed** (26.2s) ✅ |
| Typecheck frontend | — | sem script `typecheck` no `package.json` do showcase (n/a) |

Baseline **verde**. Phase 0 pronto para shipar.

> Nota sobre typecheck: o `apps/showcase/package.json` não declara um script
> `typecheck`. A correção de tipos da camada JS é coberta pelos pacotes
> `@arqel-dev/*` no monorepo (que têm seus próprios typecheck no CI raiz), não pelo
> app consumidor. Os tipos do app são validados de forma efetiva pelo `vite build`
> (que falha em erros de import/resolução) + ESLint do monorepo.

---

## Tabela de cobertura — 20 pacotes

Distribuição: composer `arqel-dev/*` + npm `@arqel-dev/*`. Os pacotes PHP `core`,
`fields`, `form`, `table`, `actions`, `marketplace`, `cli` vêm transitivamente via
o metapackage `arqel-dev/framework` (require explícito no `composer.json`); os
demais são require diretos.

| Package | Como o showcase exercita | Onde (arquivos) | Probe hints para o loop |
|---|---|---|---|
| `arqel-dev/framework` (metapackage) | Umbrella que puxa o runtime PHP inteiro; `require "^0.12.0"`. | `apps/showcase/composer.json` | Versão resolvida deve casar com o tag corrente; quebra de require transitivo aparece como classe ausente no boot. |
| `arqel-dev/core` | Painel `admin` + `PanelRegistry` + roteamento de Resources + tenancy nativa via `config('arqel.tenancy')`. | `app/Providers/ArqelServiceProvider.php`; `config/arqel.php` | Sondar `/admin`, `/admin/posts` (index/create/edit/delete). Painel registra 4 Resources via `->resources([...])`; `setCurrent('admin')`. |
| `arqel-dev/auth` | Login habilitado (`->login()` + `Arqel\Auth\Routes::register`); `/admin/login` com `admin@arqel.test` / `password`. Páginas React `LoginPage`/`Register`/`ForgotPassword`/`ResetPassword`/`VerifyEmailNotice`. | `ArqelServiceProvider.php` (linhas `->login()`, `Routes::register`); `resources/js/app.tsx` (`authPages`) | Sondar `/admin/login` (form renderiza), POST login → redirect `/admin`. Páginas auth renderizam **standalone** (sem `AppShell`). |
| `arqel-dev/fields` | Text / Slug / RichText / Select / Boolean / DateTime / KeyValue / Email / Textarea nos 4 Resources. Registro JS via `@arqel-dev/fields/register`. | `app/Arqel/Resources/*.php` (`fields()` + `form()`); `resources/js/app.tsx` (`import '@arqel-dev/fields/register'`) | Sondar create/edit de qualquer Resource: campos devem hidratar e round-trip via `fill()`/save. `PostResource` tem a maior superfície. |
| `arqel-dev/fields-advanced` | Repeater (`items` nested), Tags, Code (`json`), Markdown em `SettingResource`; RichText (`body`) + KeyValue (`meta`) em `PostResource`. Registro JS via `@arqel-dev/fields-advanced/register`. | `app/Arqel/Resources/SettingResource.php`, `PostResource.php`; `resources/js/app.tsx` | Coberto por `tests/e2e/04-advanced-fields.spec.ts` (2 cenários). Todos os campos são colunas reais (`value`/`items`/`tags` = json casts; `snippet`/`notes` = text) → persistência genuína. |
| `arqel-dev/form` | Formulários create/edit montados do schema de cada Resource, com `Section` + `->columns(2)` + `->columnSpan('full')`. | `app/Arqel/Resources/*.php` (`form()`) | Sondar layout: seções, column spans, campos `required` (ex. `title` em `PostResource`). |
| `arqel-dev/table` | Tabelas index: `TextColumn`/`BadgeColumn`/`BooleanColumn`/`DateColumn`, `SelectFilter`/`TernaryFilter`, sort, search, selectable. | `app/Arqel/Resources/*.php` (`table()`) | Sondar list/filter/sort/search — coberto por `tests/e2e/01-resources.spec.ts`. `BadgeColumn` em `status` (Post/Ticket). |
| `arqel-dev/actions` | Row actions `Actions::edit()` + `Actions::delete()`; bulk `Actions::deleteBulk()` em todos os 4 Resources. | `app/Arqel/Resources/*.php` (`->actions([...])`, `->bulkActions([...])`) | Per-row delete usa **confirm dialog**; bulk delete **não** (ver gaps abaixo). |
| `arqel-dev/nav` | Grupos de navegação na sidebar: `Content` (Post/Author), `Support` (Ticket), `System` (Setting), com `$navigationGroup`/`$navigationSort`/`$navigationIcon`. | `app/Arqel/Resources/*.php` (props estáticas `$navigation*`); `resources/js/app.tsx` (`<Sidebar>`) | Sondar a sidebar: grupos presentes, itens ordenados por `navigationSort`. |
| `arqel-dev/widgets` | `MainDashboard` registrado em `DashboardRegistry`, servido em `/admin`. 3 `StatWidget` + 1 `ChartWidget` (bar, posts by status) + 1 `TableWidget` (recent posts). | `app/Arqel/Dashboards/MainDashboard.php`; `ArqelServiceProvider.php` (`DashboardRegistry::register`) | Coberto por `tests/e2e/02-dashboard.spec.ts`. Contagens usam `withoutGlobalScopes()` (span cross-tenant). **#45 (FieldRegistry bundle split)** foi achado por este build — ver seção final. |
| `arqel-dev/tenant` | `Post` usa `BelongsToTenant` (scoping per-tenant + auto-fill de `tenant_id`); `<TenantSwitcher>` no topbar lê o prop nativo `tenant`. Resolver nativo via `config('arqel.tenancy')` (`AuthUserResolver`). | `app/Models/Post.php` (`use BelongsToTenant`); `resources/js/app.tsx` (`TenantSwitcherSlot`); migrations `tenants`/`tenant_user`/`current_tenant_id` | Coberto por `tests/e2e/03-tenant.spec.ts` (Acme → switch → Globex, posts disjuntos). |
| `arqel-dev/export` | `ExportAction::make('export')->format(ExportFormat::CSV)` como bulk action em `PostResource`. | `app/Arqel/Resources/PostResource.php` (`->bulkActions([... ExportAction ...])`) | Sondar export CSV no Post: selecionar linhas → bulk export. Único Resource com export. |
| `arqel-dev/audit` | `Post` usa `LogsActivity` (spatie/laravel-activitylog), loga atributos fillable em create/update. | `app/Models/Post.php` (`use LogsActivity`); migrations `activity_log*` | Sondar criação/edição de Post → linha no `activity_log`. Migrations de activity log são locais (publicadas). |
| `arqel-dev/versioning` | `Post` usa `Versionable` (snapshot append-only em create/update). | `app/Models/Post.php` (`use Versionable`); `config/arqel-versioning.php`; migration local `create_arqel_versions_table` | Sondar create/update de Post → snapshot em `arqel_versions`. **Atenção:** `vendor:publish` de migration está quebrado (ver gaps) — showcase usa migration local. |
| `arqel-dev/workflow` | `Ticket` usa `HasWorkflow` com `arqelWorkflow()` (estados open/in_progress/resolved sobre `status`). `TicketResource` renderiza só o status atual. | `app/Models/Ticket.php` (`use HasWorkflow` + `arqelWorkflow()`); `config/arqel-workflow.php`; migration local `create_arqel_state_transitions_table` | UI de transição guarded/history está **fora de escopo** aqui (`->transitions([])` vazio). Mesmo bug de `vendor:publish` que versioning. |
| `arqel-dev/realtime` | Broadcasting via broadcaster `log` (`BROADCAST_CONNECTION=log`) — sem custo de rede. | `config/broadcasting.php` (`'default' => env('BROADCAST_CONNECTION', 'log')`) | **Smoke apenas**: configurado, não há evento de broadcast disparado por nenhuma feature do app. Loop não deve esperar payloads de broadcast reais. |
| `arqel-dev/ai` | `StubProvider` local, determinístico, $0, offline (`default_provider => 'stub'`). | `config/arqel-ai.php`; `app/Ai/StubProvider.php` | Sondar via provider stub apenas (sem rede). Trocar para claude/openai/ollama é opt-in via `ARQEL_AI_PROVIDER`. |
| `arqel-dev/mcp` | `McpServiceProvider` auto-descoberto no boot (smoke) — expõe a superfície MCP. | auto-discovery (package discovery do Laravel) | **Smoke apenas**: sem tools/resources MCP customizados registrados no app. Loop não deve esperar superfície MCP rica aqui. |
| `arqel-dev/marketplace` | **Não exercitado.** | — | **Out-of-scope (no-spend):** requer conta Stripe real. Loop deve pular; não classificar ausência como bug. |
| `arqel-dev/cli` | **Não exercitado em runtime.** | — | É o tooling de scaffolding usado para *construir* o app, não uma superfície de runtime. Loop deve pular. |

Camada JS (npm `@arqel-dev/*`), referenciada acima onde relevante:

| Package JS | Onde |
|---|---|
| `@arqel-dev/ui` | `resources/js/app.tsx` — `AppShell`, `Sidebar`, `Topbar`, `TenantSwitcher`, `arqelPages`. Onde **#45** apareceu. |
| `@arqel-dev/react` | `resources/js/app.tsx` — `createArqelApp` (entry Inertia). |
| `@arqel-dev/hooks` | Hooks React consumidos pelo front-end Inertia (transitivo via ui/react). |
| `@arqel-dev/types` | `resources/js/app.tsx` — `TenantContextProps` (`@arqel-dev/types/tenant`). |

---

## Gaps conhecidos / candidatos de sondagem

Pontos que o loop deve sondar especificamente e candidatos a bug já identificados
durante o build deste showcase:

### 1. `vendor:publish` de migrations quebrado (versioning + workflow) — KNOWN FRAMEWORK BUG

**Pacotes:** `arqel-dev/versioning` + `arqel-dev/workflow` • **Severidade:** média • **Tem workaround:** sim

Os service providers registram a migration via Spatie `hasMigration('create_arqel_versions_table')`
(e `'create_arqel_state_transitions_table'`), que espera um arquivo
`*.php.stub` para publicar. Mas os pacotes **shipam `.php`** (migration real, não
stub):

- `packages/versioning/database/migrations/2026_05_01_000000_create_arqel_versions_table.php`
- `packages/versioning/src/VersioningServiceProvider.php:33` → `->hasMigration('create_arqel_versions_table')`
- `packages/workflow/src/WorkflowServiceProvider.php:39` → `->hasMigration('create_arqel_state_transitions_table')`

Resultado: o caminho de instalação documentado (`vendor:publish` das migrations)
**falha** para esses dois pacotes. O showcase contornou com migrations **locais**:
`database/migrations/2026_06_05_100010_create_arqel_versions_table.php` e
`2026_06_05_100011_create_arqel_state_transitions_table.php`.

→ Candidato a abrir como ticket de framework. Bloqueia o install path documentado;
tem workaround. **Ainda não filado** — registrar aqui como candidato.

### 2. Bulk delete sem confirm dialog — possível inconsistência de UX

`Actions::deleteBulk()` dispara **sem** dialog de confirmação, enquanto o
per-row `Actions::delete()` exige confirmação (coberto explicitamente por
`tests/e2e/01-resources.spec.ts` — "row delete removes a row via the confirm dialog"
vs "bulk delete removes selected rows", este último sem dialog).

→ Vale o loop classificar: **bug de framework** (inconsistência indevida) **vs
intencional** (bulk em massa não confirma por design). Presente em todos os 4
Resources (`->bulkActions([Actions::deleteBulk()])`).

### 3. `TableWidget` sem colunas explícitas

`MainDashboard` registra `TableWidget::make('recent_posts')` **sem** `->columns([...])`
— depende das colunas default derivadas do model. É um ponto duck-typed: se o
default-column resolver do `arqel-dev/widgets` regredir, o widget "recent posts"
renderiza vazio/degradado sem erro.

→ Loop deve sondar o dashboard e checar que a tabela "Recent posts" tem colunas
populadas, não só linhas.

---

## O que já foi achado + corrigido (seed do seen-registry)

**#45 — FieldRegistry bundle split (`@arqel-dev/ui`).** Foi achado **por este
build do showcase** (Task 8) e **corrigido na PR #46 (merged)**: o bundle do
`@arqel-dev/ui` precisava de `splitting: true` para que os registros de fields
(`@arqel-dev/fields/register` + `fields-advanced/register`) não colidissem com o
FieldRegistry, mais um teste de regressão.

→ O loop **não deve re-filar #45**. Já resolvido. Registrar no seen-registry.
