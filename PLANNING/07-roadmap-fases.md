# 07 — Roadmap de Fases

> Plano mestre das 4 fases de Arqel. Cada fase tem objetivos, deliverables, critérios de saída, dependências internas e externas.

## 1. Visão geral

| Fase | Nome | Duração | Tickets | Team | Status |
|---|---|---|---|---|---|
| **Fase 1** | MVP | 4-7 meses | ~120 | 2-3 devs | Planeada |
| **Fase 2** | Essenciais | 4-7 meses | ~90 | 3-4 devs | Planeada |
| **Fase 3** | Avançadas | 7-10 meses | ~70 | 3-5 devs | Planeada |
| **Fase 4** | Ecossistema | 12+ meses | ~45 | 4-6 + community | Planeada |

**Total estimado:** 27-36 meses até maturidade 1.0 completa.

## 2. Princípios de faseamento

### 2.1 Gates entre fases

Cada fase só fecha quando **todos os gates seguintes** estão verde:

- ✅ Critérios de aceite dos tickets P0/P1 todos cumpridos
- ✅ Cobertura testes ≥ target (ver RNF-Q-01/02)
- ✅ Docs críticas escritas
- ✅ Pelo menos 1 pilot user validou a fase
- ✅ Performance targets (RNF-P-*) atingidos
- ✅ Zero P0/P1 bugs abertos

### 2.2 Semantic versioning

- Fase 1 completa → **v0.5.0** (beta)
- Fase 2 completa → **v0.8.0** (RC)
- Fase 3 completa → **v1.0.0** (stable)
- Fase 4 → **v1.x+** (maintenance + ecosystem)

### 2.3 LTS strategy

A partir de v1.0:
- LTS releases anuais
- 18 meses de security patches
- 12 meses de bug fixes

## 3. Fase 1 — MVP

### 3.1 Objetivo

Entregar uma framework Laravel funcional que permita instalar via `composer require arqel-dev/framework` e construir admin panels básicos declarados em PHP, renderizados em React+ShadCN via Inertia. Foco em CRUD completo com 20 field types, tabela funcional, formulários, actions básicas, e authorization via Laravel Policies.

### 3.2 Deliverables

**Packages Composer:**
- `arqel-dev/framework` (meta-package)
- `arqel-dev/core` (ServiceProvider, Resource, Panel, CLI)
- `arqel-dev/fields` (20 field types)
- `arqel-dev/table` (DataTable, columns, filters, row actions)
- `arqel-dev/form` (FormRenderer, layout components)
- `arqel-dev/actions` (RowAction, BulkAction, ToolbarAction)
- `arqel-dev/auth` (Policy discovery)
- `arqel-dev/nav` (Navigation builder)
- `arqel-dev/testing` (Pest helpers)

**Packages npm:**
- `@arqel-dev/types`
- `@arqel-dev/react`
- `@arqel-dev/hooks`
- `@arqel-dev/ui`
- `@arqel-dev/fields`

**Artisan commands:**
- `arqel:install`
- `arqel:resource <Model>` (+ `--from-model`, `--with-policy`)
- `arqel:field <n>`
- `arqel:action <n>`
- `arqel:publish`

**Features:**
- Declarative Resources em PHP classes
- CRUD completo via Inertia (index, create, edit, show, destroy)
- 20 field types (Text, Textarea, Number, Currency, Boolean, Toggle, Select, MultiSelect, Radio, Email, URL, Password, Slug, Date, DateTime, BelongsTo, HasMany readonly, File, Image, Color, Hidden)
- Table com sort, filter, pagination, search, row actions, bulk actions
- Forms com Section, Fieldset, Grid, Group layout
- Actions com confirmation modals
- Laravel Policies integration (resource/record/field/action-level)
- Navigation declarativa com groups, icons, badges
- AppShell responsive (sidebar + topbar)
- Dark mode
- Laravel 12/13 compatibility
- PHP 8.3/8.4 compatibility
- Orchestra Testbench setup
- MIT License, DCO, Contributing guide
- GitHub CI: lint PHP (Pint), lint JS (Biome), test matrix, type check

### 3.3 Out of scope (para Fase 2+)

- Multi-tenancy
- Dashboards & widgets
- Rich text / Markdown fields
- Import/export
- Audit log
- Versioning
- Real-time features
- AI features
- MCP server
- Command palette
- Inline editing
- Virtual scrolling

### 3.4 Critérios de saída

- [ ] `composer create-project laravel/laravel demo && cd demo && composer require arqel-dev/framework && php artisan arqel:install && php artisan arqel:resource User && npm install && npm run build` funciona sem erros
- [ ] CRUD completo em <90s desde zero
- [ ] Cobertura PHP ≥ 85%, React ≥ 80%
- [ ] LCP < 1.5s em playground app
- [ ] Bundle shell < 250KB gzipped
- [ ] Docs getting-started publicadas
- [ ] 10+ GitHub stars orgânicos
- [ ] 1+ pilot user em produção (interno ou beta tester)
- [ ] PHP 8.3, 8.4 × Laravel 12, 13 × MySQL, Postgres CI matrix verde

### 3.5 Dependências externas

- Inertia.js v3 estável (ou v3 beta suficientemente maduro)
- Laravel 12 estável ✅ (já está)
- React 19.2.3+ ✅ (já está)
- ShadCN CLI v4 ✅

### 3.6 Riscos Fase 1

| Risco | Mitigação |
|---|---|
| Inertia v3 ainda beta | Testar contra v3 beta desde dia 1, ter fallback v2 pronto |
| Complexidade schema PHP → JSON | Começar simples, iterar com pilot users |
| UX forms é difícil | Copiar padrões Filament que já provaram funcionar |
| Bundle size > 250KB | Code splitting agressivo, React 19 Compiler, tree-shaking |

### 3.7 Ticket distribution (Fase 1)

| Package | Tickets | % |
|---|---|---|
| CORE | ~15 | 13% |
| FIELDS | ~25 | 21% |
| TABLE | ~15 | 13% |
| FORM | ~12 | 10% |
| ACTIONS | ~10 | 8% |
| AUTH | ~5 | 4% |
| NAV | ~5 | 4% |
| @arqel-dev/ui | ~15 | 13% |
| @arqel-dev/fields | ~10 | 8% |
| DOCS | ~5 | 4% |
| INFRA | ~3 | 3% |
| **Total** | **~120** | **100%** |

Detalhes em `08-fase-1-mvp.md`.

---

## 4. Fase 2 — Essenciais

### 4.1 Objetivo

Transformar MVP em framework completo e production-ready. Adicionar features que 80% dos projetos Laravel admin precisam: multi-tenancy, dashboards, audit, advanced fields, import/export, command palette.

### 4.2 Deliverables

**Novos packages Composer:**
- `arqel-dev/tenant` — multi-tenancy
- `arqel-dev/audit` — activity log (wraps spatie/laravel-activitylog)
- `arqel-dev/mcp` — MCP server oficial

**Extensões a packages existentes:**
- `arqel-dev/fields`: RichText (Tiptap), Markdown, Code (Shiki), Repeater, Builder, KeyValue, Tags
- `arqel-dev/table`: virtual scrolling, inline editing (TextInputColumn, SelectColumn, ToggleColumn), QueryBuilder filter, reorderable, grouping, export
- `arqel-dev/form`: Wizard multi-step, Tabs dentro de form, Split layout
- `arqel-dev/actions`: stacked modals (`<Activity>`), background queued actions, progress tracking
- `arqel-dev/core`: Dashboards + Widgets (Stat, Chart, Table, Custom), command palette

**Novos npm packages:**
- `@arqel-dev/widgets` — widget components React
- `@arqel-dev/tiptap` — RichText opt-in
- `@arqel-dev/shiki` — Code field opt-in
- `@arqel-dev/charts` — Recharts-based widgets

**Features:**
- Multi-tenancy (subdomain, path, header, session resolvers)
- Integração stancl/tenancy e spatie/laravel-multitenancy
- Dashboards com Stat + Chart + Table widgets
- Dashboard-level filters (date range aplicado a todos widgets)
- 8 field types avançados (RichText, Markdown, Code, Repeater, Builder, KeyValue, Tags, Wizard)
- Inline editing em tables
- QueryBuilder visual (AND/OR com operators)
- Virtual scrolling (TanStack Virtual)
- Stacked rows em mobile
- Export (CSV, XLSX, PDF)
- Infinite scroll (Inertia 3 merge)
- Command palette (Cmd+K)
- Column visibility persistence per-user
- Laravel Octane compatibility
- Audit log com UI de viewing
- MCP server oficial com 5+ tools (list_resources, describe_resource, generate_resource, run_test, inspect_field)
- Precognition support

### 4.3 Critérios de saída

- [ ] Multi-tenancy em produção em ≥3 pilot apps
- [ ] Dashboard demo com 5+ widgets diversos
- [ ] MCP server testado com Claude Code + Cursor
- [ ] Export de 10k records em <30s
- [ ] Virtual scrolling renderiza 100k rows smooth
- [ ] Command palette funcional com 20+ commands
- [ ] 100+ production users
- [ ] 15+ third-party plugins Composer
- [ ] 2.000+ GitHub stars

### 4.4 Dependências externas

- Laravel Reverb estável (para futura Fase 3)
- Inertia.js 3 estável (não beta)
- Spatie packages atualizados para Laravel 12+/13+

### 4.5 Ticket distribution (Fase 2)

| Área | Tickets | % |
|---|---|---|
| Multi-tenancy | ~15 | 17% |
| Advanced fields | ~20 | 22% |
| Dashboards/widgets | ~15 | 17% |
| MCP server | ~10 | 11% |
| Table enhancements | ~10 | 11% |
| Export/import | ~7 | 8% |
| Command palette | ~5 | 6% |
| Docs + recipes | ~8 | 9% |
| **Total** | **~90** | **100%** |

Detalhes em `09-fase-2-essenciais.md`.

---

## 5. Fase 3 — Avançadas

### 5.1 Objetivo

Features diferenciadoras que posicionam Arqel como line leader em inovação vs Filament e Nova: AI-native, real-time collaboration, workflow engine, semantic search, record versioning.

### 5.2 Deliverables

**Novos packages Composer:**
- `arqel-dev/versioning` — record versioning com UI restore
- `arqel-dev/workflow` — state machines (wraps spatie/laravel-model-states)
- `arqel-dev/realtime` — Reverb + Echo integration
- `arqel-dev/ai` — AI-assisted fields (Claude, OpenAI, Ollama adapters)
- `arqel-dev/search` — semantic search via pgvector
- `arqel-dev/openapi` — auto-gen OpenAPI spec para Resources

**Features:**
- AI-assisted fields (translation, summarization, classification, generation)
- Semantic search em tables (pgvector embeddings)
- Real-time collaboration (Yjs integration para text fields, presence indicators)
- Real-time widget updates via Reverb
- Record versioning com UI de restore (time-travel)
- Workflow engine (state machines com visual editor)
- AG Grid adapter opt-in (`arqel-dev/preset-grid-ag`)
- Expose Resources como REST API (auto-OpenAPI)
- Visual dashboard builder (drag-drop widgets)
- Theme inspector DevTools
- RTL support (ar, he)
- Playground online (arqel.dev/playground)
- AI field generator via MCP (describe feature → generates Resource)
- Schedule widget (Mantine Schedule / FullCalendar wrapper)

### 5.3 Critérios de saída

- [ ] AI fields em produção com ≥3 providers (Claude, OpenAI, Ollama)
- [ ] Real-time collaboration demo funciona com 5+ users simultâneos
- [ ] Workflow engine substitui patterns custom em ≥5 apps pilot
- [ ] Record versioning com diff viewer funcional
- [ ] 8.000+ GitHub stars
- [ ] Menções em Laravel News, PHP Package of the Week
- [ ] Enterprise adoption (≥2 empresas Fortune 500 ou equivalente)
- [ ] v1.0.0 estável lançado com LTS

### 5.4 Dependências externas

- Yjs maturo para real-time collab
- pgvector estável em Postgres (já está)
- LLM providers com pricing estável
- Laravel Reverb production-ready

### 5.5 Ticket distribution (Fase 3)

| Área | Tickets | % |
|---|---|---|
| AI features | ~15 | 21% |
| Real-time | ~12 | 17% |
| Workflow engine | ~10 | 14% |
| Versioning | ~8 | 11% |
| Semantic search | ~7 | 10% |
| OpenAPI gen | ~5 | 7% |
| Visual tools | ~8 | 11% |
| Docs/playground | ~5 | 7% |
| **Total** | **~70** | **100%** |

Detalhes em `10-fase-3-avancadas.md`.

---

## 6. Fase 4 — Ecossistema

### 6.1 Objetivo

Consolidar Arqel como plataforma com ecossistema vibrante: plugin marketplace, certification, community, enterprise support, Laravel Cloud integration oficial.

### 6.2 Deliverables

**Novos packages/tools:**
- `arqel-dev/devtools` — browser extension com time-travel debugging
- `arqel-dev/cli-tui` — CLI interactivo avançado (Laravel Prompts + Ink)
- `arqel-dev/pdf` — headless PDF generator para reports
- `arqel-dev/i18n-tools` — translation management UI

**Ecosystem:**
- Plugin marketplace (arqel.dev/marketplace)
- Certification program (developer certifications)
- Official Laravel Cloud integration (one-click deploy)
- Arqel Showcase (apps built with Arqel)
- Arqel Awards annual
- Premium support tier (enterprise)
- Conference talks sponsorships (Laracon, PHP conferences)

**Features adicionais:**
- Theme marketplace (themes premium)
- Component marketplace (custom fields, widgets)
- Advanced i18n tooling (translation UI, auto-sync with platforms como Crowdin)
- Advanced CLI TUI (project wizard interactivo)

### 6.3 Critérios de saída

- [ ] 15.000+ GitHub stars
- [ ] >200 production SaaS públicos usando Arqel
- [ ] Modelo económico sustentável (sponsorships + consultoria + premium support cobrindo ≥1 full-time maintainer)
- [ ] Eventualmente oficializado no Laravel ecosystem (blog mention oficial Laravel, palestra Laracon)
- [ ] Plugin marketplace com ≥50 plugins third-party
- [ ] Certification program com ≥50 certified devs

### 6.4 Ticket distribution (Fase 4)

| Área | Tickets | % |
|---|---|---|
| DevTools extension | ~8 | 18% |
| CLI TUI | ~5 | 11% |
| Marketplace | ~10 | 22% |
| Laravel Cloud | ~5 | 11% |
| PDF/Reports | ~5 | 11% |
| i18n tooling | ~5 | 11% |
| Certification | ~4 | 9% |
| Misc | ~3 | 7% |
| **Total** | **~45** | **100%** |

Detalhes em `11-fase-4-ecossistema.md`.

---

## 7. Dependências temporais entre fases

```
Fase 1 ─────────────────┐
                        │
                        ▼
                      Fase 2 ──────────┐
                                       │
                                       ▼
                                    Fase 3 ───────────┐
                                                      │
                                                      ▼
                                                    Fase 4

Overlap possível entre fases:
- Fase 1 → 2: transição suave (Fase 2 pode começar tickets de advanced fields enquanto Fase 1 finaliza)
- Fase 2 → 3: Reverb integration começa em Fase 2 (audit real-time) mas core Fase 3
- Fase 3 → 4: documentação Fase 3 pode começar cedo
```

## 8. Releases intermediários

Cada fase pode ter releases intermédios:

| Fase | Releases |
|---|---|
| Fase 1 | 0.1 (alpha), 0.2, 0.3, 0.4, 0.5 (beta complete) |
| Fase 2 | 0.6, 0.7, 0.8 (RC) |
| Fase 3 | 0.9, 1.0.0 (stable) |
| Fase 4 | 1.1, 1.2, ... (monthly minor) |

## 9. Estratégia de comunicação

### 9.1 Fase 1

- Blog post Laravel News "Introducing Arqel" em beta
- README + Getting Started como docs principais
- Discord server privado com pilot users
- Dogfooding em projeto interno

### 9.2 Fase 2

- Laravel News release post major
- Podcast appearances (Laravel Podcast, Packagist Radio)
- First conference talk (PHP UK Conference ou equivalente)
- Twitter/X threads técnicos regulares
- 5-minute intro video oficial

### 9.3 Fase 3

- Laracon talk
- Medium/dev.to posts weekly
- Showcase page com apps pilot
- Case studies de migrations (Filament → Arqel, Nova → Arqel)

### 9.4 Fase 4

- Laracon mainstage
- Laravel Cloud co-announcement
- Certification program launch
- Arqel Conf (própria conferência ou track em Laracon)

## 10. Rollback strategy

Se gate de fase falhar:

1. **Stop feature work** — não avançar para próxima fase
2. **Hardening sprint** — resolver P0/P1 bugs
3. **Pilot user feedback session** — identificar gaps
4. **Reassess scope** — talvez features P2/P3 da fase anterior subam para P1
5. **Only proceed** after gates pass

## 11. Team profile sugerido por fase

### Fase 1 (2-3 devs)

- 1 Senior Laravel dev (PHP architecture, Eloquent mastery)
- 1 Senior React dev (TypeScript, Inertia, React 19)
- 0.5-1 Full-stack dev (bridging)

### Fase 2 (3-4 devs)

Adicionar:
- 1 Frontend-specialist (advanced fields, dashboards)
- 0.5 DevRel (docs, pilot users)

### Fase 3 (3-5 devs)

Adicionar:
- 1 AI/ML engineer (AI fields, semantic search)
- 0.5 UX designer (visual tools, playground)

### Fase 4 (4-6 devs + community)

Adicionar:
- 1 DevRel full-time
- Community maintainers (plugin marketplace)
- Enterprise support engineer

## 12. Success metrics dashboard

Track mensalmente:

| Métrica | Target M6 | Target M12 | Target M24 |
|---|---|---|---|
| GitHub stars | 500 | 2.000 | 8.000 |
| Packagist installs | 1.000 | 10.000 | 100.000 |
| Production users | 10 | 100 | 1.000 |
| Third-party plugins | 2 | 15 | 50 |
| Contributors | 5 | 20 | 100 |
| Monthly GitHub Sponsors | $500 | $5.000 | $20.000 |

## 13. Próximos documentos

- **`08-fase-1-mvp.md`** — tickets detalhados Fase 1 (~120 tickets)
- **`09-fase-2-essenciais.md`** — tickets Fase 2 (~90)
- **`10-fase-3-avancadas.md`** — tickets Fase 3 (~70)
- **`11-fase-4-ecossistema.md`** — tickets Fase 4 (~45)
- **`12-processos-qa.md`** — processos, CI/CD, release
