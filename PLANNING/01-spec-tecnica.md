# 01 — Especificação Técnica

> Documento mestre de requisitos para **Arqel**. Todos os outros documentos derivam deste.

## 1. Visão e objetivos

### 1.1 Missão

Construir **Arqel** — uma framework open-source MIT de admin panels para Laravel que:

1. Combina a **declarabilidade PHP-first do Filament** (Resources, Fields, Actions em classes PHP idiomáticas) com a **modernidade React+ShadCN do Nova** (type-safety, componentes copy-paste, DX superior).
2. É **Laravel-only** — foco total, zero compromissos multi-framework.
3. Usa **Inertia.js 3** como bridge oficial — não reinventa a comunicação PHP↔JavaScript.
4. Entrega **type-safety end-to-end** do Eloquent model até aos props React via TypeScript.
5. É **AI-native** desde dia 1 (SKILL.md, AGENTS.md, MCP server oficial).
6. É **open-source MIT** — alternativa verdadeiramente gratuita ao Nova.
7. Aprende com os **erros conhecidos** de Filament e Nova e entrega-os resolvidos:
   - Filament: lock-in ao Livewire, UI difícil de customizar sem dominar Blade+Alpine, bundle size pesado
   - Nova: comercial pago, React/Vue mixing, legacy code, field ecosystem fragmentado

### 1.2 Princípios de design não-negociáveis

1. **Declarative first, imperative fallback.** Resources descrevem-se em PHP; código imperativo para casos edge.
2. **You own the frontend code.** Componentes React distribuídos via ShadCN CLI — copia-se para o projeto, é editável.
3. **Type-safety sem compromisso.** Zero `any`, zero casts manuais obrigatórios. Eloquent types → TypeScript via Laravel Data ou similar.
4. **Inertia as the bridge, not a band-aid.** Usar props, partial reloads, deferred props, infinite scrolling nativamente.
5. **Performance por default.** Bundle shell <250KB gzipped, LCP <1.5s, INP <200ms. React 19.2 Compiler habilitado.
6. **AI-native.** Cada package tem `SKILL.md`. MCP server oficial. `AGENTS.md` gerado em `arqel:install`.
7. **Eloquent-native.** Abraçamos Eloquent integralmente — não abstraímos com Repository Pattern, não evitamos casts, não assumimos outros ORMs.
8. **Filament compatibility mode.** Onde fizer sentido, APIs espelham Filament (ex: `Field::text('name')`) para reduzir custo de migração.

### 1.3 Público-alvo

**Primário:** developers Laravel intermédios-avançados a construir SaaS B2B multi-tenant, internal tools, ou ferramentas admin para clientes, que:

- Já usam ou sabem React (ou estão abertos a aprender)
- Preferem ShadCN UI sobre Material/Ant/Bootstrap
- Querem alternativa open-source ao Nova
- Estão frustrados com limitações do Filament (lock-in Livewire, dificuldade customização UI)

**Secundário:** agências/consultoras Laravel que entregam admin panels a clientes e querem:

- Customização UI sem dominar Blade/Livewire/Alpine
- Componentes React reutilizáveis entre projetos
- Open-source (não pagar licença Nova por cliente)

**Não-alvo:**
- Usuários não-programadores (low-code)
- Projetos não-Laravel (PHP vanilla, Symfony, Rails)
- Equipas sem React e sem vontade de aprender
- Equipas que preferem Livewire explícito → usem Filament

### 1.4 Métricas de sucesso (por fase)

| Fase | Métricas |
|---|---|
| Fase 1 MVP | 500+ GitHub stars • 10+ production pilots • `arqel:install` funcional em Laravel 12/13 • DX feedback colhido da comunidade Laravel |
| Fase 2 Essenciais | 2.000+ stars • 100+ production users • 15+ third-party plugins Composer • Multi-tenancy em produção |
| Fase 3 Avançadas | 8.000+ stars • Menções em Laravel News, PHP Package of the Week • Enterprise adoption • Workflow engine em produção |
| Fase 4 Ecossistema | 15.000+ stars • >200 production SaaS públicos • Modelo económico sustentável • Eventualmente oficializado no Laravel ecosystem (blog mention, conferência) |

## 2. Requisitos funcionais (RF)

### 2.1 Resources

| ID | Requisito | Fase |
|---|---|---|
| RF-R-01 | Sistema declarativo de Resources: classes PHP que estendem `Arqel\Resource` | 1 |
| RF-R-02 | Binding a Eloquent models com detecção automática de atributos/relationships | 1 |
| RF-R-03 | Lifecycle hooks: `beforeCreate`, `afterCreate`, `beforeUpdate`, `afterUpdate`, `beforeDelete`, `afterDelete`, `beforeSave`, `afterSave` | 1 |
| RF-R-04 | Registo automático via `ArqelServiceProvider` ou discovery por diretório | 1 |
| RF-R-05 | Routing auto-gerado via Inertia: `/admin/{resource}`, `/admin/{resource}/create`, `/admin/{resource}/{id}`, `/admin/{resource}/{id}/edit` | 1 |
| RF-R-06 | Policy-based authorization — integração nativa com Laravel Policies + abilities customizáveis | 1 |
| RF-R-07 | Resource scoping automático via Laravel global scopes (ex: por tenant) | 2 |
| RF-R-08 | Record versioning opcional via `arqel-dev/versioning` | 3 |
| RF-R-09 | Generation a partir de Eloquent models existentes: `php artisan arqel:resource User --from-model` | 1 |
| RF-R-10 | Generation a partir de migrations: `php artisan arqel:resource --from-migration` | 3 |
| RF-R-11 | Soft delete support nativo (detecta `SoftDeletes` trait) | 1 |
| RF-R-12 | Suporte a Eloquent casts, accessors, mutators | 1 |
| RF-R-13 | Suporte a Eloquent relationships (belongsTo, hasMany, belongsToMany, morphTo, hasOneThrough, etc.) | 1 |

### 2.2 Fields

| ID | Requisito | Fase |
|---|---|---|
| RF-F-01 | Catálogo de 20 field types básicos | 1 |
| RF-F-02 | FieldFactory fluent API compatível com convenções Filament (ex: `Field::text('name')`) | 1 |
| RF-F-03 | Validação server-side via Laravel Validation + Rule objects | 1 |
| RF-F-04 | Validação client-side espelhada via Zod 4 (gerada automaticamente de PHP rules) | 1 |
| RF-F-05 | Async validation com debounce via Inertia requests | 1 |
| RF-F-06 | Conditional/dependent fields — `dependsOn`, `hidden`, `disabled`, `visibleOn` | 1 |
| RF-F-07 | Scaffold de custom fields via CLI: `php artisan arqel:field RichMarkdown` | 1 |
| RF-F-08 | Field macros via `Field::macro()` | 1 |
| RF-F-09 | Field-level authorization (`canSee`, `canEdit`) | 1 |
| RF-F-10 | Field types avançados: RichText (Tiptap), Markdown, Code (Shiki), Repeater, Builder, KeyValue, JSON, Wizard, Tabs | 2 |
| RF-F-11 | AI-assisted fields (LLM integration) | 3 |
| RF-F-12 | File/Image fields com upload via presigned URL ou Spatie Media Library | 1 |

#### Catálogo mínimo de 20 fields (Fase 1)

Text, Textarea, Number, Currency, Boolean, Toggle, Select, MultiSelect, Radio, Email, URL, Password, Slug, Date, DateTime, BelongsTo, HasMany (readonly Fase 1), File, Image, Color, Hidden.

### 2.3 Tables

| ID | Requisito | Fase |
|---|---|---|
| RF-T-01 | DataTable com sort/filter/pagination server-side via Eloquent paginator | 1 |
| RF-T-02 | Column types: text, number, date, badge, icon, image, boolean, belongsTo, computed | 1 |
| RF-T-03 | Row actions com Inertia modals | 1 |
| RF-T-04 | Bulk actions com chunking via Laravel jobs | 1 |
| RF-T-05 | Toolbar actions (Create primary, custom) | 1 |
| RF-T-06 | Global search + column search via Eloquent Scout ou LIKE | 1 |
| RF-T-07 | Column visibility persistence per-user (stored em user settings) | 2 |
| RF-T-08 | Virtual scrolling (TanStack Virtual) para datasets grandes | 2 |
| RF-T-09 | Stacked rows em mobile breakpoint | 2 |
| RF-T-10 | Inline editing (TextInputColumn, SelectColumn, ToggleColumn) | 2 |
| RF-T-11 | Visual QueryBuilder (AND/OR, operators por tipo) | 2 |
| RF-T-12 | Grouping com summaries (sum, avg, count) | 2 |
| RF-T-13 | Reorderable (drag-drop) via campo sortOrder | 2 |
| RF-T-14 | Export (CSV, XLSX via Spatie Simple Excel ou Maatwebsite Excel, PDF via DomPDF/Snappy) | 2 |
| RF-T-15 | AG Grid adapter opt-in (`arqel-dev/preset-grid-ag`) | 3 |
| RF-T-16 | Semantic search via embeddings (Pgvector) | 3 |
| RF-T-17 | Infinite scroll via Inertia 3 `merge` prop | 2 |

### 2.4 Forms

| ID | Requisito | Fase |
|---|---|---|
| RF-FM-01 | Form builder declarativo em Resource: `schema()` method retorna array de Fields | 1 |
| RF-FM-02 | Integração Inertia Form via `useForm` hook + Laravel Validation | 1 |
| RF-FM-03 | Layout components: `Section`, `Fieldset`, `Grid`, `Columns`, `Group`, `Tabs` | 1 |
| RF-FM-04 | Wizard multi-step com state preservation via React 19.2 `<Activity>` | 2 |
| RF-FM-05 | Tabs dentro de Form | 2 |
| RF-FM-06 | Split layout (dois painéis lado a lado) | 2 |
| RF-FM-07 | Repeater (grupos de fields repetíveis, bind a hasMany) | 2 |
| RF-FM-08 | Builder (blocos heterogéneos arrastáveis, CMS-style) | 2 |
| RF-FM-09 | FormRequest classes geradas automaticamente (opcional) | 1 |
| RF-FM-10 | Precognition support (Laravel Precognition) | 2 |

### 2.5 Actions

| ID | Requisito | Fase |
|---|---|---|
| RF-A-01 | Actions unificadas: row, bulk, toolbar, detail-header, standalone | 1 |
| RF-A-02 | Action modals com forms embebidos (Inertia + ShadCN Dialog) | 1 |
| RF-A-03 | Confirmation modals com variants (info, warning, destructive) + `requireText` | 1 |
| RF-A-04 | Authorization per-action via Policies ou closure | 1 |
| RF-A-05 | Stacked action modals (state preservado via `<Activity>`) | 2 |
| RF-A-06 | Background actions com Laravel Queue + progress tracking via Reverb | 2 |
| RF-A-07 | Action chaining / workflows básicos | 3 |
| RF-A-08 | Integração workflow engine (state machines via spatie/laravel-model-states) | 3 |

### 2.6 Dashboards & Widgets

| ID | Requisito | Fase |
|---|---|---|
| RF-W-01 | Widget declarativo: classe PHP `extends Widget` com `data()` e `render` | 2 |
| RF-W-02 | Widget types: KPI (StatCard), Chart (Recharts), Table, Custom | 2 |
| RF-W-03 | Grid layout com span per-breakpoint | 2 |
| RF-W-04 | Polling automático ou manual refresh | 2 |
| RF-W-05 | Streaming SSR via Inertia 3 deferred props | 2 |
| RF-W-06 | Dashboard-level filters (ex: date range aplicado a todos widgets) | 2 |
| RF-W-07 | Widgets arrastáveis (drag-drop user-editable layout) | 3 |
| RF-W-08 | Schedule widget (wrapper Mantine Schedule ou FullCalendar) | 2 |
| RF-W-09 | Real-time widget updates via Reverb (Laravel Echo + Inertia reload) | 3 |

### 2.7 Autorização

| ID | Requisito | Fase |
|---|---|---|
| RF-AU-01 | Resource-level authorization via Laravel Policies (viewAny, view, create, update, delete, restore, forceDelete) | 1 |
| RF-AU-02 | Record-level authorization (per-row) via Policies | 1 |
| RF-AU-03 | Field-level authorization (`canSee`, `canEdit` closures com `$user` + `$record`) | 1 |
| RF-AU-04 | Action-level authorization via Policy methods customizados | 1 |
| RF-AU-05 | RBAC via integração opcional com `spatie/laravel-permission` | 1 |
| RF-AU-06 | Policy discovery automático (Laravel convention) | 1 |
| RF-AU-07 | `<CanAccess>` React component para conditional rendering no frontend | 1 |
| RF-AU-08 | Gate-based authorization (Laravel Gates) suportado | 1 |

### 2.8 Multi-tenancy

| ID | Requisito | Fase |
|---|---|---|
| RF-MT-01 | Tenancy config declarativa em `config/arqel.php` | 2 |
| RF-MT-02 | Tenant resolver via subdomain, path, header, ou session | 2 |
| RF-MT-03 | Scoping automático de queries via Eloquent global scopes | 2 |
| RF-MT-04 | Tenant switcher component no AppShell React | 2 |
| RF-MT-05 | Tenant registration flow scaffolder | 2 |
| RF-MT-06 | Tenant profile page scaffolder | 2 |
| RF-MT-07 | Integração opcional com `stancl/tenancy` (multi-database tenancy) | 2 |
| RF-MT-08 | Integração opcional com `spatie/laravel-multitenancy` | 2 |
| RF-MT-09 | `scopedUnique` validation rule | 2 |
| RF-MT-10 | White-labeling por tenant (cores, logo, fonts via CSS vars injection) | 2 |
| RF-MT-11 | Billing via Cashier (Stripe/Paddle) adapter | 2 |

### 2.9 Navegação

| ID | Requisito | Fase |
|---|---|---|
| RF-N-01 | Navigation declarativa em `ArqelServiceProvider::boot()` | 1 |
| RF-N-02 | Auto-registo de resources no nav (opt-in) | 1 |
| RF-N-03 | Groups collapsibles, dividers, badges | 1 |
| RF-N-04 | Ícones via Lucide React + Heroicons compatibility | 1 |
| RF-N-05 | Condicional visibility por Policy | 1 |
| RF-N-06 | Breadcrumbs automáticos a partir da rota Inertia | 1 |
| RF-N-07 | AppShell composição (sidebar, topbar, content) responsive | 1 |
| RF-N-08 | Command palette (Cmd+K) com navegação, create, global search, actions | 2 |
| RF-N-09 | Favoritos do usuário | 2 |

### 2.10 Tema e customização

| ID | Requisito | Fase |
|---|---|---|
| RF-TH-01 | Tailwind v4 + CSS variables base | 1 |
| RF-TH-02 | Dark mode (system/toggle/persistente via cookie) | 1 |
| RF-TH-03 | Design System Presets (ShadCN CLI v4 style: amber-zinc, slate, etc.) | 2 |
| RF-TH-04 | Customização via data-attributes sem eject | 1 |
| RF-TH-05 | Layout variants (sidebar-left, sidebar-right, topbar-only, full-width) | 2 |
| RF-TH-06 | Theme inspector no DevTools | 3 |
| RF-TH-07 | Per-tenant theming (CSS vars injected via Inertia shared props) | 2 |

### 2.11 Performance

| ID | Requisito | Fase |
|---|---|---|
| RF-P-01 | Code splitting per-resource via Vite chunks | 1 |
| RF-P-02 | React 19.2 Compiler habilitado | 1 |
| RF-P-03 | Eloquent eager loading automático (detecção `with()` via Fields) | 1 |
| RF-P-04 | Inertia partial reloads para props updates específicos | 1 |
| RF-P-05 | Inertia deferred props para widgets pesados | 2 |
| RF-P-06 | Optimistic UI via Inertia 3 optimistic updates | 1 |
| RF-P-07 | Debounced search/filter (300ms default) | 1 |
| RF-P-08 | Virtual scrolling em tables | 2 |
| RF-P-09 | Laravel Octane compatibility (FrankenPHP, Swoole, RoadRunner) | 2 |
| RF-P-10 | Cache de policies + navigation computed | 2 |

### 2.12 Developer Experience

| ID | Requisito | Fase |
|---|---|---|
| RF-DX-01 | Artisan `arqel:install` configura tudo num comando | 1 |
| RF-DX-02 | Artisan `arqel:resource <Model>` gera Resource class | 1 |
| RF-DX-03 | Artisan `arqel:field <Name>` gera custom field (PHP + React component) | 1 |
| RF-DX-04 | Artisan `arqel:action <Name>` | 1 |
| RF-DX-05 | Artisan `arqel:widget <Name>` | 2 |
| RF-DX-06 | SKILL.md por package Composer | 1 |
| RF-DX-07 | MCP server oficial (`arqel-dev/mcp`) | 2 |
| RF-DX-08 | AGENTS.md auto-gerado em `arqel:install` | 1 |
| RF-DX-09 | DevTools browser extension | 3 |
| RF-DX-10 | Mensagens de erro acionáveis com links para docs | 1 |
| RF-DX-11 | Playground online (arqel.dev/playground) | 3 |
| RF-DX-12 | Laravel IDE Helper integration (auto-gen docblocks) | 1 |
| RF-DX-13 | PhpStan/Larastan-compatible (nível 8+) | 1 |

### 2.13 Testing

| ID | Requisito | Fase |
|---|---|---|
| RF-TS-01 | `arqel-dev/testing` Composer package com test helpers | 1 |
| RF-TS-02 | Pest 3 + PHPUnit 11 compatibility | 1 |
| RF-TS-03 | Orchestra Testbench integration | 1 |
| RF-TS-04 | `actingAsAdmin()`, `visitResource()`, `submitForm()` helpers | 1 |
| RF-TS-05 | Inertia testing via `Inertia::assertInertia()` | 1 |
| RF-TS-06 | Vitest + Testing Library para React components | 1 |
| RF-TS-07 | Playwright recipes para E2E full-stack | 2 |
| RF-TS-08 | Visual regression via Storybook + Chromatic | 3 |

### 2.14 Inovações (Fase 3+)

| ID | Requisito | Fase |
|---|---|---|
| RF-IN-01 | AI-assisted fields (Claude, OpenAI, Ollama adapters) | 3 |
| RF-IN-02 | Semantic search em tables via pgvector | 3 |
| RF-IN-03 | Real-time collaboration (Laravel Reverb + Yjs integration) | 3 |
| RF-IN-04 | Audit log nativo (integra spatie/laravel-activitylog) | 2 |
| RF-IN-05 | Record versioning com UI de restore | 3 |
| RF-IN-06 | Workflow engine (state machines via spatie/laravel-model-states) | 3 |
| RF-IN-07 | Schema-driven dev (Eloquent model → Resource automático) | 1 |
| RF-IN-08 | Auto-OpenAPI spec gen (expor Resources como API) | 3 |
| RF-IN-09 | Plugin marketplace (arqel.dev/marketplace) | 4 |
| RF-IN-10 | Visual dashboard builder | 3 |

### 2.15 Ecossistema (Fase 4)

| ID | Requisito | Fase |
|---|---|---|
| RF-EC-01 | CLI TUI interactivo avançado (Laravel Prompts + Ink) | 4 |
| RF-EC-02 | DevTools browser extension com time-travel debugging | 4 |
| RF-EC-03 | Laravel Cloud integration oficial | 4 |
| RF-EC-04 | Headless PDF generator para reports | 4 |
| RF-EC-05 | i18n tooling avançado (translation management UI) | 4 |
| RF-EC-06 | Certification program | 4 |

## 3. Requisitos não-funcionais (RNF)

### 3.1 Performance

| ID | Métrica | Target | Como medir |
|---|---|---|---|
| RNF-P-01 | Shell bundle gzipped (Inertia + React + ShadCN base) | < 250 KB | Vite build analyzer, CI check |
| RNF-P-02 | Initial JS served | < 200 KB | Lighthouse CI |
| RNF-P-03 | TTFB (empty cache) | < 200 ms | Lighthouse |
| RNF-P-04 | LCP | < 1.5 s | Lighthouse, RUM |
| RNF-P-05 | INP | < 200 ms | Lighthouse, RUM |
| RNF-P-06 | Table render 10k rows | < 50 ms per frame | Playwright perf tests |
| RNF-P-07 | Form 50 fields keystroke | < 100 ms | Playwright perf tests |
| RNF-P-08 | `arqel:install` + first render | < 90 s | E2E test |
| RNF-P-09 | Eloquent queries por page | N+1-free garantido | Laravel Debugbar CI check |

### 3.2 Qualidade de código

| ID | Requisito | Como enforçar |
|---|---|---|
| RNF-Q-01 | Cobertura de testes PHP > 85% | Pest coverage CI gate |
| RNF-Q-02 | Cobertura de testes React > 80% | Vitest coverage CI gate |
| RNF-Q-03 | Zero `mixed` sem justificação em PHP | Larastan nível 8 |
| RNF-Q-04 | Zero `any` em TypeScript | `@typescript-eslint/no-explicit-any: error` |
| RNF-Q-05 | PHP 8.3+ strict types em todos os arquivos | `declare(strict_types=1);` obrigatório |
| RNF-Q-06 | TypeScript strict (strict, noUncheckedIndexedAccess, exactOptionalPropertyTypes) | tsconfig |
| RNF-Q-07 | Laravel Pint em CI | Pre-commit hook + CI |
| RNF-Q-08 | Biome lint+format para TS/TSX | CI |
| RNF-Q-09 | Conventional Commits | commitlint |
| RNF-Q-10 | PSR-12 code style | Pint |

### 3.3 Segurança

| ID | Requisito | Fase |
|---|---|---|
| RNF-S-01 | React 19.2.3+ obrigatório (CVE-2025-55182) | 1 |
| RNF-S-02 | Laravel 12.30+ mínimo (last security patch) | 1 |
| RNF-S-03 | Dependency scanning: Dependabot, Renovate, composer audit em CI | 1 |
| RNF-S-04 | SAST via GitHub CodeQL | 1 |
| RNF-S-05 | Security policy (SECURITY.md) + responsible disclosure | 1 |
| RNF-S-06 | XSS protection em rich-text fields (HtmlPurifier ou bleach) | 2 |
| RNF-S-07 | CSRF via Laravel VerifyCsrfToken (Inertia handles) | 1 |
| RNF-S-08 | SQL injection impossível (Eloquent prepared statements enforced) | 1 |
| RNF-S-09 | Mass assignment protection via `$fillable`/`$guarded` validation | 1 |
| RNF-S-10 | Policies enforced server-side sempre (client-side é UX, não boundary) | 1 |
| RNF-S-11 | Rate limiting em actions destructivas via `throttle` middleware | 1 |

### 3.4 Acessibilidade

| ID | Requisito | Fase |
|---|---|---|
| RNF-A-01 | WCAG 2.2 AA em todos os componentes core React | 1 |
| RNF-A-02 | Keyboard navigation completa | 1 |
| RNF-A-03 | Screen reader testing (NVDA, VoiceOver, JAWS) | 2 |
| RNF-A-04 | Focus management em modals, command palette, stacked actions | 1 |
| RNF-A-05 | `prefers-reduced-motion` respeitado | 2 |
| RNF-A-06 | Auditoria externa WCAG AA | 3 |

### 3.5 Compatibilidade

| ID | Requisito |
|---|---|
| RNF-C-01 | PHP 8.3+ obrigatório (PHP 8.4 recomendado) |
| RNF-C-02 | Laravel 12.x+ (13.x quando estável) |
| RNF-C-03 | React 19.2.3+ |
| RNF-C-04 | Node.js 20.9+ para build |
| RNF-C-05 | TypeScript 5.5+ |
| RNF-C-06 | Browsers: Chrome 111+, Edge 111+, Firefox 111+, Safari 16.4+ |
| RNF-C-07 | MySQL 8.0+, PostgreSQL 14+, SQLite 3.40+ (dev), MariaDB 10.6+ |
| RNF-C-08 | Inertia.js 3+ obrigatório |
| RNF-C-09 | Vite 6+ como bundler |
| RNF-C-10 | Laravel Octane compatible (FrankenPHP, Swoole, RoadRunner) |

### 3.6 Internacionalização

| ID | Requisito | Fase |
|---|---|---|
| RNF-I-01 | UI strings externalizadas via Laravel lang files | 1 |
| RNF-I-02 | 8 línguas default (en, pt-PT, pt-BR, es, fr, de, it, ja) | 2 |
| RNF-I-03 | Resource labels traduzíveis via `__()` | 1 |
| RNF-I-04 | Frontend translations sincronizadas via Inertia shared props | 1 |
| RNF-I-05 | RTL support (ar, he) | 3 |
| RNF-I-06 | Date/number/currency formatting via Laravel Carbon + Intl | 1 |

### 3.7 Documentação

| ID | Requisito | Fase |
|---|---|---|
| RNF-D-01 | Getting Started em < 10 min | 1 |
| RNF-D-02 | API reference auto-gerada (PHP docblocks + TypeDoc) | 1 |
| RNF-D-03 | >30 recipes cookbook | 2 |
| RNF-D-04 | Migration guides: from Filament, from Nova, from React Admin (para React devs) | 2 |
| RNF-D-05 | Video tutorials oficiais | 2 |
| RNF-D-06 | Interactive examples em todas as páginas de docs | 2 |
| RNF-D-07 | Playground online | 3 |

### 3.8 Licenciamento e governança

| ID | Requisito |
|---|---|
| RNF-G-01 | MIT License |
| RNF-G-02 | CLA não requerido (DCO apenas) |
| RNF-G-03 | Code of Conduct (Contributor Covenant 2.1) |
| RNF-G-04 | Contributing Guide |
| RNF-G-05 | Governance Model documentado |
| RNF-G-06 | Monorepo público no GitHub desde dia 1 (ou beta) |

## 4. Constraints e assumptions

### 4.1 Constraints

- **Budget.** Sustentação via GitHub Sponsors + consultoria + cursos; sem VC.
- **Team size.** 2-6 devs conforme fase; full-time maintainer desde M1.
- **Dependências externas críticas.**
  - Inertia.js v3 — se stagnar, temos problema. Mitigação: manter compatibility com v2 até v3 maduro (6m+).
  - ShadCN CLI v4 — se breaking change, pinnar versão e escalonar upgrade.
  - Laravel 13 — se atrasado, ficar em Laravel 12 (já suficiente).
- **PHP 8.3+ obrigatório** — readonly classes, typed class constants, json_validate() são assumidos.
- **React 19.2.3+ obrigatório** — CVE-2025-55182 + Compiler + `<Activity>`.

### 4.2 Assumptions

- Laravel continua a ser o framework PHP dominante.
- Inertia.js continua a ter backing do Laravel team (Taylor Otwell support).
- ShadCN CLI v4 mantém-se em linha com releases de Março 2026.
- Radix UI continua com manutenção ativa.
- Comunidade Laravel está aberta a React (evidência: Nova usa Vue+Inertia, Breeze oferece React starter).

### 4.3 Riscos identificados

| Risco | Impacto | Mitigação |
|---|---|---|
| Inertia.js v3 atrasa estabilização | Alto | Ship com v2 e migrar (v2 é maduro) |
| Filament responde com Inertia+React | Médio | Focar em diferenciadores (AI-native, ownership via ShadCN) |
| Nova abre source (improvável) | Alto | Focar em DX e comunidade; Nova historicamente não mudará modelo |
| Laravel 13 introduz breaking changes grandes | Médio | Manter Laravel 12 support durante ≥12 meses |
| Comunidade Laravel rejeita React | Médio | DevRel forte + migration guides + pilot users |
| ShadCN CLI muda contrato breaking | Alto | Pinnar versão, adapter layer interno |
| Burnout maintainer | Alto | Governance + dinheiro honesto + rotation |
| Segurança: CVE em dep crítica | Crítico | Dependabot daily, resposta <48h |

## 5. Fora do âmbito

Arqel **não** é:

1. **Framework PHP** — é package Laravel.
2. **Livewire-based** — conflita filosoficamente com a escolha React.
3. **Multi-PHP framework** (Symfony, Yii, CakePHP) — Laravel-only.
4. **Multi-frontend framework** (Vue, Svelte, Solid) — React-only.
5. **Backend API standalone** — usa Laravel routes normais via Inertia; expor API REST é trabalho do user.
6. **CMS headless** (Strapi/Payload alternative) — não competimos aí.
7. **Form builder drag-drop** para end-users (dev-time apenas).
8. **Low-code / no-code** — target audience é developers.
9. **Hosted SaaS Arqel** — somos framework, não plataforma.
10. **Substituto do Filament** — coexistimos; Filament tem o seu nicho (Livewire-first).

## 6. Glossário

| Termo | Definição |
|---|---|
| **Resource** | Classe PHP que estende `Arqel\Resource` e define Fields, Actions, Policies para um Eloquent model. |
| **Field** | Definição declarativa de um atributo do Resource: tipo, validação, rendering React component. |
| **Action** | Operação invocável sobre um ou mais records (publish, archive, impersonate, custom). |
| **Policy** | Laravel Policy class com methods de authorization (view, create, update, delete, + custom abilities). |
| **Panel** | Conjunto de Resources + Navigation + Theme sob uma mesma rota base (ex: `/admin`, `/dashboard`). Arqel suporta múltiplos panels. |
| **Inertia Props** | Mecanismo Inertia.js de passar dados do controller PHP para o componente React. |
| **Shared Props** | Props globais injectadas em todas as páginas Inertia (user auth, tenant, flash messages). |
| **Tenancy** | Subsistema que scope-ia dados por tenant via Eloquent global scopes. |
| **App Shell** | Layout top-level (sidebar + topbar + content area). |
| **Preset** | Design system config partilhável (cores, fonts, radius) — ShadCN CLI v4. |
| **Skill (SKILL.md)** | Arquivo por package Composer com contexto para coding agents. |
| **MCP** | Model Context Protocol — standard para expor ferramentas a LLMs. |
| **Widget** | Componente de dashboard (KPI, chart, table, custom). |
| **Artisan Command** | Command-line Laravel command via `php artisan`. |

## 7. Dependências mínimas runtime (resume)

### 7.1 composer.json meta-package (arqel-dev/framework)

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0|^13.0",
        "inertiajs/inertia-laravel": "^3.0",
        "spatie/laravel-package-tools": "^1.16",
        "livewire/flux": "optional",
        "arqel-dev/core": "self.version",
        "arqel-dev/fields": "self.version",
        "arqel-dev/table": "self.version",
        "arqel-dev/form": "self.version",
        "arqel-dev/actions": "self.version"
    },
    "suggest": {
        "spatie/laravel-permission": "For RBAC authorization",
        "spatie/laravel-medialibrary": "For advanced file/image fields",
        "spatie/laravel-activitylog": "For audit logs"
    }
}
```

### 7.2 package.json core (@arqel-dev/ui)

```json
{
    "peerDependencies": {
        "@inertiajs/react": "^3.0",
        "react": "^19.2.3",
        "react-dom": "^19.2.3"
    },
    "dependencies": {
        "radix-ui": "^1.0",
        "lucide-react": "^0.400",
        "class-variance-authority": "^0.7",
        "clsx": "^2.1",
        "tailwind-merge": "^3.0"
    }
}
```

## 8. Aprovações

| Role | Nome | Data |
|---|---|---|
| Product Owner | _a definir_ | — |
| Tech Lead | _a definir_ | — |
| Design Lead | _a definir_ | — |
