# 02 — Arquitectura

> Arquitectura técnica de **Arqel**. Complementa `01-spec-tecnica.md` com diagramas C4, fluxos de dados, e decisões estruturais. Decisões individuais justificadas em `03-adrs.md`.

## 1. Visão geral

Arqel é um **package Laravel monolítico distribuído como mono-repo Composer + npm**, que combina:

- **Camada PHP** (primária): Service Provider, Resources, Fields, Actions, Policies — tudo declarativo e idiomático Laravel.
- **Camada Inertia**: bridge oficial que serializa props PHP → JSON → React sem API intermédia.
- **Camada React** (apresentação): componentes ShadCN-based, type-safe via TypeScript, distribuídos via npm packages + ShadCN CLI.

A arquitectura **não é SPA com API separada**. É **monolito Laravel com frontend JavaScript moderno** — o padrão "Modern Monolith" que Inertia.js promove.

## 2. C4 Level 1 — System Context

```
┌──────────────────────────────────────────────────────────────────┐
│                                                                   │
│   ┌──────────────┐           ┌──────────────────┐                │
│   │              │           │                  │                │
│   │  Admin User  │─────────▶│  Browser (React) │                 │
│   │ (developer   │  HTTPS    │                  │                │
│   │  or end-user │           │                  │                │
│   │  admin)      │           └────────┬─────────┘                │
│   └──────────────┘                    │                          │
│                                       │ Inertia XHR / full load  │
│                                       ▼                          │
│              ┌────────────────────────────────────┐              │
│              │                                    │              │
│              │      Laravel App + Arqel           │◀──┐          │
│              │      (this system)                 │   │          │
│              │                                    │   │          │
│              └──────┬───────────┬─────────┬───────┘   │          │
│                     │           │         │            │          │
│                     ▼           ▼         ▼            │          │
│              ┌──────────┐ ┌────────┐ ┌────────┐       │          │
│              │ Database │ │ Redis  │ │Storage │       │          │
│              │(Postgres │ │(cache, │ │(S3/    │       │          │
│              │  /MySQL) │ │ queue) │ │ local) │       │          │
│              └──────────┘ └────────┘ └────────┘       │          │
│                                                        │          │
│              ┌──────────────────┐                     │          │
│              │  LLM providers   │                     │          │
│              │ (Claude, OpenAI) │─────────────────────┘          │
│              └──────────────────┘  (Fase 3+)                     │
│                                                                   │
│              ┌──────────────────┐                                │
│              │   MCP clients    │                                │
│              │ (Claude Code,    │                                │
│              │  Cursor, etc.)   │                                │
│              └──────────────────┘                                │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
```

### Actores externos

| Actor | Interação |
|---|---|
| **Admin user** (developer ou end-user admin) | Usa browser React para CRUD via Arqel |
| **Developer** | Instala via Composer + npm, define Resources em PHP |
| **LLM providers** (Fase 3+) | Via AI fields para completion/generation |
| **MCP clients** (Claude Code, Cursor) | Via `arqel/mcp` server para introspecção e geração de código |

### Sistemas externos

| Sistema | Propósito |
|---|---|
| **Database** (Postgres/MySQL/MariaDB/SQLite) | Persistência via Eloquent |
| **Redis** | Cache, sessions, queue driver, broadcasting |
| **Storage** (S3, local, GCS via Flysystem) | File/image uploads |
| **Queue workers** | Background jobs (bulk actions, exports, notifications) |
| **Laravel Reverb** (Fase 3+) | WebSockets para real-time features |

## 3. C4 Level 2 — Containers

```
┌────────────────────────────────────────────────────────────────────────┐
│                         Arqel System                                    │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │ Browser (SPA-like)                                                │  │
│  │                                                                    │  │
│  │  ┌────────────────┐  ┌──────────────┐  ┌─────────────────┐      │  │
│  │  │ Arqel React    │  │  Inertia     │  │  ShadCN UI      │      │  │
│  │  │ Components     │─▶│  Client      │◀─│  (user-owned    │      │  │
│  │  │ (@arqel/ui,    │  │  (router,    │  │   copies)       │      │  │
│  │  │  @arqel/fields)│  │   forms)     │  │                 │      │  │
│  │  └────────────────┘  └──────────────┘  └─────────────────┘      │  │
│  │           │                   │                                   │  │
│  │           └───────────┬───────┘                                  │  │
│  │                       │                                           │  │
│  └───────────────────────┼───────────────────────────────────────────┘  │
│                          │ Inertia XHR (JSON)                           │
│                          │ OR full HTML (first load)                    │
│                          ▼                                              │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │ Laravel Application Server                                        │  │
│  │                                                                    │  │
│  │  ┌────────────────────────────────────────────────────────────┐  │  │
│  │  │ Routes (web.php + Arqel::routes())                          │  │  │
│  │  └─────────────────────────┬──────────────────────────────────┘  │  │
│  │                            │                                       │  │
│  │  ┌─────────────────────────▼──────────────────────────────────┐  │  │
│  │  │ Middleware Stack (auth, tenant, Inertia)                    │  │  │
│  │  └─────────────────────────┬──────────────────────────────────┘  │  │
│  │                            │                                       │  │
│  │  ┌─────────────────────────▼──────────────────────────────────┐  │  │
│  │  │ Arqel Controllers (ResourceController, ActionController)     │  │  │
│  │  └──┬─────────────┬────────────┬──────────────┬──────────────┘  │  │
│  │     │             │            │              │                   │  │
│  │     ▼             ▼            ▼              ▼                   │  │
│  │  ┌──────┐   ┌─────────┐  ┌─────────┐   ┌─────────┐               │  │
│  │  │Resource│  │ Fields  │  │Policies │   │ Actions │               │  │
│  │  │Registry│  │Schema   │  │ Gate    │   │Executor │               │  │
│  │  └───┬──┘   └────┬────┘  └────┬────┘   └────┬────┘               │  │
│  │      │           │             │             │                     │  │
│  │      └───────────┴─────────────┴─────────────┘                    │  │
│  │                             │                                      │  │
│  │  ┌──────────────────────────▼───────────────────────────────────┐ │  │
│  │  │ Eloquent ORM                                                  │ │  │
│  │  │ (Models, Relationships, Casts, Scopes)                       │ │  │
│  │  └──────────────────────────┬───────────────────────────────────┘ │  │
│  │                             │                                      │  │
│  │  ┌──────────────────────────▼───────────────────────────────────┐ │  │
│  │  │ Database Connection (Postgres/MySQL/SQLite)                   │ │  │
│  │  └──────────────────────────────────────────────────────────────┘ │  │
│  │                                                                    │  │
│  │  ┌─────────────────┐  ┌──────────────┐  ┌──────────────────────┐ │  │
│  │  │ Queue Worker    │  │ Broadcasting │  │ MCP Server           │ │  │
│  │  │ (bulk actions)  │  │ (Reverb)     │  │ (introspection)      │ │  │
│  │  └─────────────────┘  └──────────────┘  └──────────────────────┘ │  │
│  │                                                                    │  │
│  └────────────────────────────────────────────────────────────────────┘  │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

### Containers explicados

| Container | Tecnologia | Responsabilidade |
|---|---|---|
| **Arqel React Components** | React 19.2 + TypeScript | UI declarativa, recebe Inertia props, renderiza ShadCN-based |
| **Inertia Client** | `@inertiajs/react` v3 | Router, form handling, link prefetch, partial reloads |
| **ShadCN UI (user-owned)** | React components copiados para `resources/js/components/ui` | Primitives visuais, editáveis pelo usuário |
| **Laravel Application Server** | PHP 8.3+ + Laravel 12/13 + FPM/Octane | Servidor principal |
| **Arqel Controllers** | PHP classes em `arqel/core` | Recebem requests, invocam Resource logic, retornam `Inertia::render()` |
| **Resource Registry** | PHP Singleton | Mapeia Resource classes → rotas, URLs, navigation |
| **Fields Schema** | PHP objects | Serialização dos Fields em array JSON-safe para Inertia |
| **Policies Gate** | Laravel Gate + Policies | Authorization checks |
| **Actions Executor** | PHP class | Invoca Actions, gere queuing, progress tracking |
| **Eloquent ORM** | Laravel Eloquent | CRUD, relationships, casts, scopes |
| **Queue Worker** | Laravel Horizon ou queue:work | Bulk actions, exports, notifications |
| **Broadcasting** | Laravel Reverb + Echo | Real-time updates (Fase 3) |
| **MCP Server** | `arqel/mcp` package | Expõe introspection tools para LLMs |

## 4. C4 Level 3 — Components (Arqel packages internos)

### 4.1 Packages Composer

```
arqel/arqel (meta-package)
    │
    ├── arqel/core
    │     ├── ArqelServiceProvider
    │     ├── ResourceRegistry
    │     ├── Contracts/
    │     │     ├── HasResource
    │     │     ├── HasFields
    │     │     ├── HasActions
    │     │     └── HasPolicies
    │     ├── Http/Middleware/HandleArqelInertiaRequests
    │     └── Concerns/ (traits)
    │
    ├── arqel/fields
    │     ├── Field (base class)
    │     ├── TextField, NumberField, SelectField, ...
    │     ├── FieldFactory (static fluent API)
    │     └── ValidationBridge (PHP rules → Zod schema)
    │
    ├── arqel/table
    │     ├── Table
    │     ├── Column (text, badge, image, computed, ...)
    │     ├── Filter (select, dateRange, text, ...)
    │     └── TablePaginator (Eloquent paginator wrapper)
    │
    ├── arqel/form
    │     ├── Form
    │     ├── FormSchema (ordered fields + layout)
    │     ├── Layout/ (Section, Fieldset, Grid, Tabs, Wizard)
    │     └── FormRequest (generator)
    │
    ├── arqel/actions
    │     ├── Action (base)
    │     ├── RowAction, BulkAction, ToolbarAction
    │     ├── ConfirmableAction trait
    │     └── ActionExecutor (jobs integration)
    │
    ├── arqel/auth (authorization)
    │     ├── PolicyDiscovery
    │     └── Ability registry
    │
    ├── arqel/nav
    │     ├── Navigation (declarative builder)
    │     ├── NavigationItem
    │     └── NavigationGroup
    │
    ├── arqel/tenant (Fase 2)
    │     ├── TenantResolver
    │     ├── TenantScopedQuery (global scope)
    │     └── Integrations/ (stancl, spatie)
    │
    ├── arqel/audit (Fase 2)
    │     └── (wraps spatie/laravel-activitylog)
    │
    ├── arqel/versioning (Fase 3)
    │     └── Versionable trait
    │
    ├── arqel/workflow (Fase 3)
    │     └── (wraps spatie/laravel-model-states)
    │
    ├── arqel/realtime (Fase 3)
    │     └── Reverb integration + Echo channels
    │
    ├── arqel/mcp (Fase 2)
    │     └── MCP server (stdio + HTTP)
    │
    └── arqel/testing
          └── Pest + Testbench helpers
```

### 4.2 Packages npm

```
@arqel/types        ← TypeScript types espelhando PHP schemas
@arqel/hooks        ← React hooks: useResource, useArqelForm, useCanAccess
@arqel/ui           ← AppShell, Sidebar, Topbar, DataTable, FormRenderer
@arqel/fields       ← Field components React (TextInput, Select, ...)
@arqel/react        ← Inertia bindings + utilities
```

## 5. Fluxos de dados críticos

### 5.1 Fluxo: Listar records (GET /admin/users)

```
1. Browser: user navega /admin/users
2. Inertia Client: faz GET /admin/users com header X-Inertia
3. Laravel Router: resolve → Arqel\Http\ResourceController@index
4. Middleware: auth, tenant scope, Inertia
5. ResourceController:
   a. Lê Resource class de ResourceRegistry (UserResource)
   b. Verifica Policy::viewAny($user) — se false, aborta 403
   c. Aplica filters da query string (?filter[status]=active)
   d. Eloquent paginator com eager loading auto-detectado dos Fields
   e. Serializa records via Resource::toArray() + field-level authorization
   f. Retorna Inertia::render('arqel::resources/index', [...])
6. Inertia: serializa props JSON, envia resposta
7. Browser: @arqel/ui renderiza <DataTable /> com props recebidos
```

### 5.2 Fluxo: Submit form (POST /admin/users)

```
1. Browser: user submete form (Inertia useForm.post())
2. Inertia: POST /admin/users com dados form + X-Inertia
3. Laravel: Arqel\Http\ResourceController@store
4. Middleware: auth, tenant, CSRF auto-validated
5. ResourceController:
   a. Policy::create($user) — se false, 403
   b. FormRequest auto-gerado (ou custom) valida input
   c. Cria Eloquent model, aplica casts, relationships
   d. Executa Resource::afterCreate() hook
   e. Se sucesso: Inertia::location('/admin/users/{id}') ou redirect
   f. Se erro: back() com ValidationErrors
6. Browser: Inertia recebe, atualiza DOM ou mostra erros
```

### 5.3 Fluxo: Bulk action assíncrona

```
1. Browser: user selecciona 1000 records, clica "Export CSV"
2. Inertia POST /admin/users/actions/export { ids: [...] }
3. ResourceController::action:
   a. Authorization
   b. Dispatch BulkActionJob → Queue
   c. Retorna Inertia partial com job_id
4. Browser: <ProgressToast /> subscreve channel arqel.action.{job_id}
5. Queue worker: processa job em background, emite eventos via Reverb
6. Browser: Reverb WebSocket → atualiza progress real-time
7. Job completo: download URL enviado, toast success
```

### 5.4 Fluxo: Campo dependente (dependsOn)

```
1. User digita em "country" field (form page)
2. React debounces 300ms, detecta dependentes ("state" field)
3. Inertia: faz partial reload com only: ['options.state']
4. Laravel: ResourceController::formSchema refresca apenas "state" options
5. Response: JSON parcial com novos options
6. Inertia merge: <Select name="state"> recebe options actualizadas
```

## 6. Decisões arquiteturais-chave

### 6.1 Inertia como única bridge

**Decisão:** Inertia.js v3 é a única bridge suportada. Sem fallback Livewire, sem API REST opcional.

**Porquê:**
- Nova v5 valida esta escolha (mesmo que use Vue).
- Elimina duplicação validation (Laravel rules rule — client-side é espelho Zod derivado, não source of truth).
- Permite type-safety E2E (PHP Resource → Inertia props → React component).
- Bundle size mínimo (no Axios, usa XHR nativo em v3).

**Trade-offs:**
- Não funciona para mobile native apps (que precisariam de API REST). → Usuários que queiram expor API fazem-no separadamente em `routes/api.php`.

### 6.2 Laravel-only, Eloquent-native

**Decisão:** suportamos Laravel 12+ exclusivamente. Eloquent é o único ORM considerado.

**Porquê:**
- Filament tem 20k+ stars Laravel-only; validação empírica.
- Multi-framework dilui foco catastroficamente (complexidade exponencial).
- Eloquent tem conveniences (scopes, casts, relationships) que um abstraction layer DB-agnostic mataria.

**Consequência:** Resources dependem de Eloquent models. Não suportamos "plain DB queries" ou outros ORMs.

### 6.3 Inertia props vs API interna

**Decisão:** Inertia props são o default. Sem TanStack Query default. Sem exposição de API REST gerada por Arqel.

**Porquê:**
- Simplicidade — um único mecanismo de data-flow.
- Inertia 3 tem partial reloads, deferred props, optimistic updates nativas — cobre ≥90% dos cenários.
- Real-time via Reverb + Echo (Fase 3) — separado do ciclo Inertia.

**Escape hatches:**
- Users podem definir rotas `/api/*` custom.
- `useHttp` hook do Inertia 3 para requests fora do ciclo de página.

### 6.4 ShadCN CLI v4 distribution

**Decisão:** componentes visuais são distribuídos em **duas camadas**:

1. **npm package `@arqel/ui`** — exporta componentes estruturais (AppShell, DataTable shell, FormRenderer).
2. **ShadCN CLI v4 via `arqel.dev/r/*`** — componentes atómicos (Button, Input, Select, etc.) são copiados para `resources/js/components/ui/*` do user.

**Porquê:**
- Base UI / ShadCN CLI é o padrão moderno React 2026.
- User owns the code dos primitives — customização sem hacks.
- Structural components via npm para updates semânticos.

### 6.5 Monorepo Composer + npm

**Decisão:** um único Git repo (`github.com/arqel/arqel`) contém todos packages Composer e npm.

**Porquê:**
- Releases coordenados (PHP changes + TS types changes em mesmo PR).
- Single source of truth para docs, CI, issues.
- Precedente: Filament, Nova, Laravel próprio (monorepo `laravel/framework`).

**Estrutura:** ver `04-repo-structure.md`.

### 6.6 Laravel Policies como authorization canónica

**Decisão:** authorization é sempre `Gate::authorize()` / `$user->can()` via Laravel Policies. Não reinventamos.

**Porquê:**
- Padrão Laravel, familiar à comunidade.
- Integra nativamente com RBAC (Spatie Permission).
- Testable via `actingAs()`.

**Consequência:** client-side `<CanAccess>` é **UX only** — boundary real é sempre server-side.

### 6.7 MCP como first-class

**Decisão:** `arqel/mcp` é um package Composer que expõe MCP tools (introspection + codegen) desde Fase 2.

**Porquê:**
- Diferenciador forte vs Filament/Nova.
- Habilita AI-native development (Claude Code, Cursor podem gerar Resources corretamente).
- MCP é standard crescente em 2026.

## 7. Decisões de contrato entre camadas

### 7.1 PHP → Inertia Props shape

Toda Resource renderiza via `Inertia::render('arqel::{view}', [...])` com props estruturadas:

```json
{
    "resource": {
        "name": "User",
        "pluralName": "Users",
        "slug": "users",
        "urls": { "index": "/admin/users", "create": "/admin/users/create", ... }
    },
    "records": { /* Eloquent paginator resource */ },
    "fields": [ /* Field schemas */ ],
    "actions": [ /* Action definitions */ ],
    "filters": [ /* Filter definitions */ ],
    "can": { "create": true, "update": true, ... },
    "flash": { "success": "...", "error": "..." }
}
```

### 7.2 Field schema (PHP → JSON)

```json
{
    "type": "text",
    "name": "email",
    "label": "Email Address",
    "placeholder": "user@example.com",
    "required": true,
    "readonly": false,
    "validation": { /* Zod-compatible schema */ },
    "ui": { "component": "EmailInput", "props": {...} }
}
```

### 7.3 TypeScript companion types

Geramos types PHP → TypeScript via `spatie/laravel-typescript-transformer` ou similar:

```typescript
// Auto-generated from UserResource.php
export interface UserRecord {
    id: number
    email: string
    name: string
    created_at: string
}
```

Usuários têm `@arqel/types` com shape genérico + types específicos gerados.

## 8. Estratégia de rendering

### 8.1 SSR (Server-Side Rendering)

**Default:** ligado. Inertia 3 + Vite plugin fazem SSR automático em dev e prod.

**Porquê:**
- SEO não é relevante para admin panels, mas **First Contentful Paint** é.
- Reduz tempo até interactividade.
- Opt-out via config para quem não precisar.

### 8.2 Partial reloads (key feature Inertia)

Arqel usa partial reloads extensivamente:

- Filters em tables → `only: ['records', 'filters']`
- Field `dependsOn` → `only: ['fields.{field_name}.options']`
- Actions → `only: ['records']` (refresh após action)
- Dashboard widgets independentes → `only: ['widgets.{id}']`

### 8.3 Deferred props (Fase 2+)

Widgets pesados carregam via Inertia deferred props — página renderiza primeiro sem widgets, depois cada widget carrega assincronamente.

## 9. Performance & otimizações

### 9.1 Bundle splitting

Vite config:

```typescript
{
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'arqel-core': ['@arqel/ui', '@arqel/react', '@inertiajs/react'],
                    'arqel-table': ['@arqel/table', '@tanstack/react-table'],
                    'arqel-form': ['@arqel/form', 'zod', 'react-hook-form'],
                    'react-vendor': ['react', 'react-dom']
                }
            }
        }
    }
}
```

Cada Resource lazy-loaded via `resolve` callback Inertia.

### 9.2 Eloquent eager loading automático

`Resource::indexQuery()` detecta Fields com relationships e adiciona `with()` automaticamente:

```php
Field::belongsTo('company', Company::class)
// → auto ->with('company')

Column::belongsTo('author')->display('name')
// → auto ->with('author:id,name')
```

### 9.3 Cache de policies + navigation

Navigation menu é computed por `$user` uma vez por request, cached em Laravel Cache (5 min TTL). Invalidated em role change.

### 9.4 React 19.2 Compiler

Habilitado por default em Vite config via `babel-plugin-react-compiler`. Elimina necessidade de `useMemo`/`useCallback` manual.

## 10. Deploy topology

### 10.1 Dev local

```
Developer machine
├── PHP 8.3+ + Composer
├── Node 20+ + npm/pnpm
├── Laravel Valet / Herd / Sail
├── SQLite ou Postgres local
└── `npm run dev` (Vite HMR) + `php artisan serve`
```

### 10.2 Produção small/medium

```
Single server (VPS, Laravel Forge, etc.)
├── Nginx + PHP-FPM 8.3
├── Laravel Octane (FrankenPHP) — opcional mas recomendado
├── Queue worker (Horizon)
├── Redis
├── Postgres
└── Vite build (static assets servidos por Nginx)
```

### 10.3 Produção large

```
Load balancer (multiple app servers)
├── App servers (stateless, Octane + FrankenPHP)
├── Redis cluster (cache, sessions, queue)
├── Postgres primary + replicas
├── S3 para storage
├── CDN (Cloudflare) para static assets
├── Horizon em servidor dedicado
└── Reverb em servidor dedicado (WebSockets)
```

### 10.4 Laravel Cloud (Fase 4)

Integration oficial — one-click deploy de Arqel apps para Laravel Cloud.

## 11. Observabilidade

### 11.1 Logging

- Laravel Log channels (stack, daily, papertrail, slack).
- Contextual logs em Actions e Policies.

### 11.2 Monitoring

- Laravel Telescope (dev + staging).
- Laravel Pulse (prod) — built-in por Laravel team.
- Sentry / Flare integration opt-in.
- OpenTelemetry adapter (Fase 4).

### 11.3 Metrics

- Slow query detection via Eloquent events.
- Response time histograms.
- Bundle size tracking em CI.

## 12. Segurança (arquitectural)

### 12.1 Authentication

- Laravel's built-in auth (Breeze, Fortify, ou custom).
- Arqel **não implementa auth** — assume auth já configurada.
- Suporta Laravel Sanctum para SPA tokens.

### 12.2 Authorization

- Todas as rotas Arqel atrás do middleware `arqel.auth`.
- Policies enforced em toda operação mutadora.
- Field-level auth serializado na resposta Inertia (removido antes de serializar).
- Audit log opcional via `arqel/audit`.

### 12.3 CSRF

- Laravel VerifyCsrfToken middleware + Inertia CSRF handling automático.

### 12.4 XSS

- React escapa por default.
- Rich-text fields sanitized via HtmlPurifier no backend antes de persist.

### 12.5 Rate limiting

- Actions destructivas (bulk delete) throttled via `throttle:60,1` middleware.
- API-like endpoints opt-in throttle.

## 13. Testing topology

```
┌─────────────────────────────────────────────────────┐
│                                                       │
│  ┌────────────┐  ┌────────────┐  ┌───────────────┐  │
│  │  Pest +    │  │  Vitest    │  │  Playwright   │  │
│  │  PHPUnit   │  │  +         │  │  E2E          │  │
│  │            │  │  Testing   │  │               │  │
│  │  • PHP     │  │  Library   │  │  • Inertia    │  │
│  │    units   │  │            │  │    flows      │  │
│  │  • Inertia │  │  • Hooks   │  │  • CRUD full  │  │
│  │    tests   │  │  • Comps   │  │    scenarios  │  │
│  │  • Policies│  │            │  │  • Accessib.  │  │
│  └────────────┘  └────────────┘  └───────────────┘  │
│                                                       │
│  ┌─────────────────────────────────────────────────┐ │
│  │  Orchestra Testbench (Laravel package testing)   │ │
│  └─────────────────────────────────────────────────┘ │
│                                                       │
│  ┌─────────────────────────────────────────────────┐ │
│  │  GitHub Actions matrix:                           │ │
│  │  PHP 8.3, 8.4 × Laravel 12, 13 × MySQL, Postgres │ │
│  └─────────────────────────────────────────────────┘ │
│                                                       │
└──────────────────────────────────────────────────────┘
```

Detalhes em `12-processos-qa.md`.

## 14. Upgrade & migration

### 14.1 SemVer rigoroso

- `MAJOR` — breaking changes (novos requisitos PHP/Laravel, API changes).
- `MINOR` — features additivas backward-compat.
- `PATCH` — bugfixes.

### 14.2 Deprecation policy

- 1 minor version de deprecation warning antes de remoção.
- Logged via `trigger_error(E_USER_DEPRECATED)`.
- Migration CLI tool: `php artisan arqel:upgrade --from=0.9 --to=1.0`.

### 14.3 Breaking change windows

- Major releases anuais (máximo).
- LTS release a partir de 1.0 com 18 meses de security patches.

## 15. Próximos documentos

- **`03-adrs.md`** — Architecture Decision Records individuais com rationale.
- **`04-repo-structure.md`** — layout detalhado do monorepo.
- **`05-api-php.md`** — contratos PHP (Resource, Field, Action classes).
- **`06-api-react.md`** — contratos TypeScript.
