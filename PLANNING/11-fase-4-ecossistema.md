# 11 — Fase 4 (Ecossistema): Tickets Detalhados

> Lista completa de tickets para a **Fase 4 (Ecossistema)** do Arqel. Consolidar Arqel como plataforma com ecossistema vibrante: plugin marketplace, certification, community infrastructure, enterprise support, Laravel Cloud integration oficial.

## Índice

1. [Visão geral da fase](#1-visão-geral-da-fase)
2. [DevTools browser extension (DEVTOOLS)](#2-devtools-browser-extension-devtools)
3. [CLI TUI avançado (CLI-TUI)](#3-cli-tui-avançado-cli-tui)
4. [Plugin marketplace (MKTPLC)](#4-plugin-marketplace-mktplc)
5. [Laravel Cloud integration (LCLOUD)](#5-laravel-cloud-integration-lcloud)
6. [Headless PDF generator (PDF)](#6-headless-pdf-generator-pdf)
7. [Advanced i18n tooling (I18N)](#7-advanced-i18n-tooling-i18n)
8. [Certification program (CERT)](#8-certification-program-cert)
9. [Misc & community (MISC)](#9-misc--community-misc)
10. [Ordem sugerida de execução](#10-ordem-sugerida-de-execução)

## 1. Visão geral da fase

**Objetivo** (ver `07-roadmap-fases.md` §6): consolidar Arqel como plataforma com ecossistema vibrante. Fase 4 é ongoing — começa após v1.0 mas continua iterativamente.

**Duração:** 12+ meses com 4-6 devs + community contributors.

**Total de tickets Fase 4:** ~45, distribuídos:

| Pacote | Tickets | % |
|---|---|---|
| DEVTOOLS | 8 | 18% |
| CLI-TUI | 5 | 11% |
| MKTPLC | 10 | 22% |
| LCLOUD | 5 | 11% |
| PDF | 5 | 11% |
| I18N | 5 | 11% |
| CERT | 4 | 9% |
| MISC | 3 | 7% |

**Critérios de saída** (ver `07-roadmap-fases.md` §6.3):
- 15.000+ GitHub stars
- >200 production SaaS públicos usando Arqel
- Modelo econômico sustentável (sponsorships + consultoria + premium support cobrindo ≥1 full-time maintainer)
- Eventualmente oficializado no Laravel ecosystem (blog mention oficial Laravel, palestra Laracon)
- Plugin marketplace com ≥50 plugins third-party
- Certification program com ≥50 certified devs

**Releases esperados:** v1.x+ (monthly minors, patches conforme necessário)

---

## 2. DevTools browser extension (DEVTOOLS)

### [DEVTOOLS-001] Setup extensão Chrome/Firefox

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** react • **Depende de:** [UI-002] (Fase 1)

**Contexto**

Cobre RF-EC-02 e RF-DX-09. DevTools extension injeta painel debug em browser — útil para inspeção runtime de Inertia state, policies, fields.

**Descrição técnica**

Estrutura `packages/devtools-extension/`:

- `manifest.json` v3 (Chrome/Edge/Firefox compatible)
- `src/background.ts` (service worker)
- `src/content-script.ts` (injeta em páginas)
- `src/devtools.html` + `src/devtools.ts` (painel DevTools)
- `src/panel/` (React app dentro do DevTools tab)
- Vite build config específico para extensão
- Icons em 16/32/48/128px
- Publish em Chrome Web Store + Firefox Add-ons

**Critérios de aceite**

- [ ] Extensão instalável em Chrome + Firefox + Edge
- [ ] Manifest v3 correto
- [ ] Ícones nos 4 tamanhos
- [ ] Abre tab "Arqel" dentro do DevTools browser
- [ ] Build reproducible via `pnpm build:extension`
- [ ] Submissão Chrome Web Store aprovada

**Notas de implementação**

- Manifest v3 é mandatory 2026+ (v2 deprecated).
- Firefox ainda aceita manifest v2 com compat — usar polyfill (`webextension-polyfill`).

---

### [DEVTOOLS-002] Detecção de Arqel em página + injeção

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** react • **Depende de:** [DEVTOOLS-001]

**Contexto**

Extensão só ativa quando detecta Arqel rodando na página.

**Descrição técnica**

Content script verifica presença de:

```javascript
window.__ARQEL_DEVTOOLS_HOOK__ // Injetado pelo @arqel/react em dev mode
```

Se presente, extensão fica "active" (ícone colorido). Senão, "inactive" (ícone cinza).

Hook no `@arqel/react`:

```typescript
// @arqel/react/devtools.ts
if (import.meta.env.DEV) {
    window.__ARQEL_DEVTOOLS_HOOK__ = {
        version: ARQEL_VERSION,
        getState: () => ({
            panel: currentPanel,
            resource: currentResource,
            sharedProps: pageProps,
        }),
        subscribe: (callback) => { /* ... */ },
    };
}
```

**Critérios de aceite**

- [ ] Extension detecta Arqel em página
- [ ] Ícone muda cor conforme active/inactive
- [ ] Hook só injetado em dev mode (não produção — security)
- [ ] Teste: página com e sem Arqel

**Notas de implementação**

- **Crítico:** hook nunca em prod. `import.meta.env.DEV` via Vite filtra no build.

---

### [DEVTOOLS-003] Painel: Inertia state inspector

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** react • **Depende de:** [DEVTOOLS-002]

**Contexto**

Primeiro tab útil: inspect Inertia page props + shared props em tempo real.

**Descrição técnica**

Painel React com:

- Árvore JSON expandable dos page props atuais
- Shared props separately
- Search filter
- "Copy to clipboard" botão
- History: previous navigations (last 20)
- Timing info: request duration

Connection via `chrome.runtime` messaging entre devtools panel e content script.

**Critérios de aceite**

- [ ] Live state view
- [ ] Expand/collapse nodes
- [ ] Search filter works
- [ ] Navigation history
- [ ] Teste: mudar de página, verify update

---

### [DEVTOOLS-004] Painel: Policy debugger

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** react + php • **Depende de:** [DEVTOOLS-003]

**Contexto**

Bug comum: user não vê ação esperada. Por quê? Policy falhando. DevTools mostra todas policies checadas na página + resultado.

**Descrição técnica**

PHP side em dev mode: log todas authorization checks:

```php
// In ArqelServiceProvider (dev only)
Gate::after(function ($user, $ability, $result, $arguments) {
    if (app()->isLocal()) {
        // Accumulate in request-scoped log
        app('arqel.devtools.policy-log')->push([
            'ability' => $ability,
            'arguments' => $arguments,
            'result' => $result,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
        ]);
    }
});
```

Log exposto via shared props (dev only):

```php
'__devtools' => app()->isLocal() ? [
    'policyLog' => $policyLog,
    'queryCount' => DB::getQueryLog(),
    'memoryUsage' => memory_get_peak_usage(true),
] : null,
```

Painel renderiza table: ability, arguments, result, stack trace.

**Critérios de aceite**

- [ ] All policy checks logged em dev
- [ ] Painel mostra table
- [ ] Click row → expand stack trace
- [ ] Filter by result (allow/deny)
- [ ] Teste: navegar, verify policies listed

**Notas de implementação**

- Production must NOT expose policy log (leak info).
- Env check via `app()->environment('local')` is reliable.

---

### [DEVTOOLS-005] Painel: Field schema inspector

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** react • **Depende de:** [DEVTOOLS-003]

**Contexto**

Inspecionar fields de Resource atual: types, validation, visibility rules.

**Descrição técnica**

Tab "Fields" no painel:

- Lista fields serializados
- Per field: type, validation rules, dependencies, visibility
- Click field → detailed view
- "Resend with different value" (útil para testing)

**Critérios de aceite**

- [ ] Fields listados
- [ ] Detailed view funcional
- [ ] Teste: navigate to create page, see fields

---

### [DEVTOOLS-006] Painel: Time-travel debugging (inspired React DevTools)

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** XL • **Camada:** react • **Depende de:** [DEVTOOLS-003]

**Contexto**

Time-travel: snapshot state em cada Inertia navigation, ability to jump back.

**Descrição técnica**

Hook em `@arqel/react`:

```typescript
window.__ARQEL_DEVTOOLS_HOOK__.snapshot = () => {
    // Capture current state
};

// On each Inertia navigation:
router.on('navigate', () => {
    hook.pushSnapshot({
        timestamp: Date.now(),
        url: window.location.pathname,
        props: currentPageProps,
    });
});
```

Painel tab "Time Travel":

- Timeline of navigations
- Click to preview
- "Jump to" not literally possible (HTTP re-request), but "replay" via Inertia visit

**Critérios de aceite**

- [ ] Navigations captured
- [ ] Timeline renderizado
- [ ] Preview per snapshot
- [ ] Teste: navigate 5 pages, verify all captured

---

### [DEVTOOLS-007] Painel: Performance metrics

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** react • **Depende de:** [DEVTOOLS-003]

**Contexto**

Mostra: LCP, INP, bundle size, query count, memory.

**Descrição técnica**

- Performance API Web Vitals
- Query log via shared props dev mode
- Bundle chunks analyzed via webpack stats OR Vite manifest
- Render time per Inertia navigation

**Critérios de aceite**

- [ ] LCP, INP, FID, CLS exibidos
- [ ] Query count + slow queries highlighted
- [ ] Bundle breakdown
- [ ] Teste: slow page shows warning

---

### [DEVTOOLS-008] Testes + docs DevTools extension

**Tipo:** test + docs • **Prioridade:** P0 • **Estimativa:** M • **Camada:** react + docs • **Depende de:** [DEVTOOLS-007]

**Descrição técnica**

- Tests para extension logic (manifest, messaging)
- Manual tests em Chrome + Firefox + Edge
- Docs: install, features, troubleshooting

**Critérios de aceite**

- [ ] Coverage ≥ 70% (extensions harder to test)
- [ ] Manual test checklist passed
- [ ] Docs publicadas em arqel.dev/devtools

---

## 3. CLI TUI avançado (CLI-TUI)

### [CLI-TUI-001] Setup project scaffolder interactivo

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** L • **Camada:** php • **Depende de:** [CORE-003] (Fase 1)

**Contexto**

Cobre RF-EC-01. `arqel:new` command que cria projeto novo Laravel + Arqel com scaffolding interactivo.

**Descrição técnica**

Standalone CLI tool (composer global): `arqel/cli`.

```bash
composer global require arqel/cli
arqel new my-app
```

Flow interativo (Laravel Prompts):

```
? What's the app name? [my-admin]
? Which starter kit?
  > Breeze (React)
    Jetstream (Inertia React)
    None (manual)
? Enable multi-tenancy? (y/n)
  > y
? Tenancy strategy?
  > Simple (scoped models)
    stancl/tenancy (multi-DB)
    spatie/laravel-multitenancy
? Install first Resource scaffolding? (y/n)
  > y
? Model name for first resource?
  > User
? Enable dark mode? (y/n)
  > y
? Use MCP integration? (y/n)
  > y
```

Executa:
1. `laravel new {name}`
2. `cd {name}`
3. Starter kit setup
4. `composer require arqel/arqel`
5. Packages adicionais based on answers
6. `php artisan arqel:install` + flags
7. `pnpm install`
8. Write `.env.example` com vars Arqel
9. Display next steps

**Critérios de aceite**

- [ ] Comando interactivo funciona
- [ ] Todos branches testados (Breeze, Jetstream, nenhum, multi-tenancy variants)
- [ ] Full scaffolding <5 min
- [ ] Teste: generate 3 variantes, verify apps work

**Notas de implementação**

- Laravel Prompts v0.2+ para richer UI.
- Network-heavy: Composer + npm installs. Oferecer "--offline" mode? Probably not worth.

---

### [CLI-TUI-002] Interactive Resource generator

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** L • **Camada:** php • **Depende de:** [CLI-TUI-001, CORE-009] (Fase 1)

**Contexto**

Fase 1 tem `arqel:resource` básico. Fase 4 adiciona mode interactivo.

**Descrição técnica**

`arqel:resource` sem args abre wizard:

```
? Model class? (autocomplete from App\Models\*)
  > App\Models\Post
? Resource label?
  > Post
? Navigation group?
  > Content
? Icon? (autocomplete Lucide icons)
  > file-text
? Fields detected from model: [preview]
  ▸ title (text)
  ▸ slug (slug)
  ▸ body (textarea)
  ▸ published_at (dateTime)
  ▸ author_id (belongsTo → UserResource)
? Confirm fields? (y/n/e [edit])
  > y
? Generate Policy?
  > y
? Generate FormRequest classes?
  > n
? Tests?
  > Pest feature test
```

Outputs files, shows diff preview, asks confirmation.

**Critérios de aceite**

- [ ] Interactive mode funcional
- [ ] Preview antes de write
- [ ] Cancela limpo (sem files criados)
- [ ] Teste: guided creation de Post resource

---

### [CLI-TUI-003] Ink-based rich terminal UI

**Tipo:** feat • **Prioridade:** P2 • **Estimativa:** XL • **Camada:** node • **Depende de:** [CLI-TUI-001]

**Contexto**

Laravel Prompts é bom mas limitado. Ink (React para CLI) permite UIs mais ricas.

**Descrição técnica**

Alternative CLI em Node/TypeScript com Ink:

- Dashboard mode: `arqel dashboard` em terminal com stats (queries/sec, active users, errors)
- Resource browser: navigate resources, records, fields via terminal
- Live log tailing
- Interactive artisan commands

Separate package `@arqel/cli-ink`:

```bash
npm i -g @arqel/cli-ink
arqel-ink
```

**Critérios de aceite**

- [ ] Ink UI funciona cross-platform (mac, linux, windows)
- [ ] Dashboard mode live-update
- [ ] Resource browser navigable
- [ ] Teste: interactive session sem crash

**Notas de implementação**

- Ink v5+ para React 19 support.
- Complex ticket — only P2 because Laravel Prompts cobre 80% use cases.

---

### [CLI-TUI-004] Arqel Doctor — diagnostic command

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [CORE-002] (Fase 1)

**Contexto**

User com instalação broken? `php artisan arqel:doctor` diagnostica.

**Descrição técnica**

Checks:

- PHP version ≥ 8.3
- Laravel version ≥ 12
- Node version ≥ 20.9
- Required packages installed (composer)
- Required npm packages installed
- ServiceProvider registered
- Config published
- Migrations run
- Assets built (Vite)
- Storage links
- Queue worker running (warning if not)
- Reverb configured (if realtime package)
- Permissions em storage/ e bootstrap/cache/

Output colorido com 🟢 OK, 🟡 Warning, 🔴 Error + suggested fix.

**Critérios de aceite**

- [ ] Todos checks implementados
- [ ] Clear output com fixes
- [ ] `--fix` flag attempts auto-repair
- [ ] Teste: break install, run doctor, verify detected

---

### [CLI-TUI-005] Docs + SKILL.md CLI-TUI

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** S • **Camada:** docs • **Depende de:** [CLI-TUI-004]

**Descrição técnica**

Docs guides:
- `arqel new` flow
- Interactive resource generator
- Doctor
- Advanced Ink UI (opcional)

**Critérios de aceite**

- [ ] Guides publicados

---

## 4. Plugin marketplace (MKTPLC)

### [MKTPLC-001] Backend: marketplace schema + API

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [CORE-008] (Fase 1)

**Contexto**

Cobre RF-IN-09. Plugin marketplace = site separado (`arqel.dev/marketplace`) onde community publica + users descobrem plugins.

**Descrição técnica**

Backend (Laravel app separado hospedando marketplace):

- `Plugin` model: name, slug, description, type (field, widget, integration, theme), author, composer_package, npm_package, github_url, license, screenshots, version
- `PluginVersion` model: versioning history
- `Installation` tracking (anonymized)
- `Review` model: stars, comment
- API REST para listing, filters, search

**Critérios de aceite**

- [ ] Schema implementado
- [ ] API endpoints: list, detail, search, reviews
- [ ] Admin interface for publishers
- [ ] Teste: register + publish plugin flow

**Notas de implementação**

- Marketplace pode ser Arqel-powered (dogfood).
- Hosted em Laravel Cloud inicialmente.

---

### [MKTPLC-002] Plugin submission workflow

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [MKTPLC-001]

**Contexto**

Publishers submetem plugins; approval workflow ensures quality + security.

**Descrição técnica**

Flow:

1. Publisher cria conta em arqel.dev/marketplace
2. "Submit plugin" form:
   - Composer package name (validate exists em Packagist)
   - NPM package if applicable
   - GitHub repo URL
   - Type + category
   - Description
   - Screenshots
3. Auto-checks:
   - composer.json válido
   - Follows Arqel plugin conventions (namespace, service provider pattern)
   - Tests present
   - README complete
4. Manual review (1-7 dias)
5. Approved → published
6. Weekly security audit (Renovate-like)

**Critérios de aceite**

- [ ] Submission form funcional
- [ ] Auto-checks implementados
- [ ] Admin review queue
- [ ] Status emails aos publishers
- [ ] Teste: submit + approve flow

---

### [MKTPLC-003] Plugin metadata convention (composer/npm package)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [MKTPLC-001]

**Contexto**

Publishers seguem convention; Arqel CLI e marketplace consomem metadata.

**Descrição técnica**

`composer.json` plugin convention:

```json
{
    "name": "acme/arqel-stripe-fields",
    "type": "arqel-plugin",
    "keywords": ["arqel", "plugin", "stripe", "payments"],
    "extra": {
        "arqel": {
            "plugin-type": "field-pack",
            "compat": {
                "arqel": "^1.0"
            },
            "installation-instructions": "See README.md",
            "category": "integrations"
        }
    }
}
```

Plugin types: `field-pack`, `widget-pack`, `theme`, `integration`, `language-pack`, `tool`.

Arqel CLI command `arqel:plugin:list` lists installed Arqel plugins.

**Critérios de aceite**

- [ ] Convention documented
- [ ] Validator implementado
- [ ] CLI command funcional
- [ ] Teste: check existing plugin against convention

---

### [MKTPLC-004] Frontend: marketplace site

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** XL • **Camada:** react • **Depende de:** [MKTPLC-001]

**Contexto**

UI pública para discovery.

**Descrição técnica**

Arqel-powered app hosted em `arqel.dev/marketplace` (ou separate subdomain). Built com Arqel próprio (dogfooding):

Páginas:

- Landing: featured plugins, categories, search
- Browse: filter by type, category, compat version
- Detail: readme, screenshots, installation instructions, reviews, versions
- Compare: select 2-3 plugins side-by-side
- Publisher profile
- Categories pages (Fields, Widgets, Themes, Integrations, Tools)

**Critérios de aceite**

- [ ] Search funcional
- [ ] Filters responsivos
- [ ] Plugin detail rich
- [ ] SEO optimized (SSR)
- [ ] Mobile friendly
- [ ] Teste E2E: browse, search, view detail

---

### [MKTPLC-005] Install flow via CLI

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [MKTPLC-003]

**Contexto**

User pode instalar plugin diretamente via CLI:

```bash
arqel install acme/arqel-stripe-fields
```

**Descrição técnica**

Command:
1. Fetch plugin metadata from marketplace API
2. Check compatibility with current Arqel version
3. Run `composer require {package}` + `npm install {package}` (if applicable)
4. Run plugin-specific installer if exists (`php artisan {plugin-name}:install`)
5. Display next steps

**Critérios de aceite**

- [ ] Command implementado
- [ ] Compatibility check
- [ ] Installation flow smooth
- [ ] Teste: install 3 plugins variados

---

### [MKTPLC-006] Reviews + ratings system

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php + react • **Depende de:** [MKTPLC-001]

**Descrição técnica**

- 5-star rating
- Textual review
- Verified purchaser flag (for paid plugins Fase 4+)
- Review moderation
- Helpful/unhelpful votes

**Critérios de aceite**

- [ ] Submit review funcional
- [ ] Display reviews com sort (helpful, recent, rating)
- [ ] Moderation queue
- [ ] Teste: submit + moderate

---

### [MKTPLC-007] Categorization + trending

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [MKTPLC-001]

**Contexto**

Discovery precisa curadoria. Trending = plugins ganhando tração.

**Descrição técnica**

- Categories: Fields, Widgets, Themes, Integrations, Utilities
- Featured section (editor's picks)
- Trending: score = (downloads last 7 days) × (recent reviews positive)
- "New this week"
- "Most popular"

**Critérios de aceite**

- [ ] Categories structure
- [ ] Trending algorithm
- [ ] Featured admin control
- [ ] Teste: trending actualiza daily

---

### [MKTPLC-008] Premium plugins (paid)

**Tipo:** feat • **Prioridade:** P2 • **Estimativa:** XL • **Camada:** php • **Depende de:** [MKTPLC-006]

**Contexto**

Publishers podem cobrar por plugins. Revenue share com Arqel (ex: 80/20).

**Descrição técnica**

- Stripe Connect (ou similar) para payouts
- License keys para buyers
- Download restricted to paid customers
- Revenue dashboard para publishers
- Arqel revenue share config

**Critérios de aceite**

- [ ] Payment flow funcional (Stripe Connect)
- [ ] License keys generated + validated
- [ ] Publisher dashboard
- [ ] Teste: purchase → download → use plugin

**Notas de implementação**

- Legal/tax considerations heavy — consult lawyer antes.
- Start sem premium em Fase 4 early; add depois validated demand.

---

### [MKTPLC-009] Plugin security scanning

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [MKTPLC-002]

**Contexto**

Plugins are code — malicious code = security disaster. Auto-scan obrigatório.

**Descrição técnica**

Daily scan de todos plugins publicados:

- Composer audit (known vulnerabilities)
- npm audit
- Static analysis: code patterns suspeitos (eval, exec, file_get_contents de user input, etc.)
- License check
- Dependency tree audit

Findings:
- Critical → plugin auto-delisted + notify publisher + security advisory
- High → yellow warning badge
- Medium/low → logged, notify publisher

**Critérios de aceite**

- [ ] Daily scan implementado
- [ ] Vulnerability DB integration (GitHub Advisory Database)
- [ ] Auto-delist for critical findings
- [ ] Publisher notification
- [ ] Teste: seed vulnerability, verify detection

---

### [MKTPLC-010] Marketplace docs + publisher guide

**Tipo:** docs • **Prioridade:** P0 • **Estimativa:** L • **Camada:** docs • **Depende de:** [MKTPLC-009]

**Descrição técnica**

Docs:
- How to find plugins (user guide)
- How to publish (publisher guide)
- Plugin development tutorial (from scratch)
- Security best practices
- Payment setup (premium, Fase 4+)

**Critérios de aceite**

- [ ] Publisher guide step-by-step
- [ ] Development tutorial funcional (seguir cria plugin real)
- [ ] Security doc completa

---

## 5. Laravel Cloud integration (LCLOUD)

### [LCLOUD-001] Arqel-ready Laravel Cloud template

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** infra • **Depende de:** [CORE-003] (Fase 1)

**Contexto**

Cobre RF-EC-03. Laravel Cloud (Taylor Otwell's SaaS) é o deploy default recomendado 2026. Arqel tem integration oficial.

**Descrição técnica**

Template repository + button "Deploy to Laravel Cloud":

- `github.com/arqel/laravel-cloud-template`
- Pre-configured: Arqel installed, sample Resources, DB seeded, Reverb configured
- `cloud.yml` template com:
  - PHP 8.3
  - Node 20
  - Redis
  - Postgres + pgvector
  - Reverb service
- Documentation: one-click deploy button

**Critérios de aceite**

- [ ] Template repo live
- [ ] Deploy button funciona (redireciona Laravel Cloud)
- [ ] App running em <5 min após deploy
- [ ] Teste: deploy template, verify functional

---

### [LCLOUD-002] Cloud-specific optimizations

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [LCLOUD-001]

**Contexto**

Laravel Cloud tem features específicas (auto-scaling, queue workers managed, Reverb hosted).

**Descrição técnica**

Config detection:

```php
// config/arqel.php
'cloud' => [
    'enabled' => env('LARAVEL_CLOUD', false),
    'auto_configure' => true,
],
```

Quando detected:
- Storage disk default: S3 managed
- Cache driver: Redis managed
- Queue connection: Laravel Cloud queue service
- Reverb URL automatic
- Log drivers: Laravel Cloud logging

**Critérios de aceite**

- [ ] Auto-config em Laravel Cloud environments
- [ ] Zero config para users
- [ ] Teste: deploy minimal app, verify config auto

---

### [LCLOUD-003] Monitoring integration

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [LCLOUD-001]

**Contexto**

Laravel Pulse é built-in Laravel. Cloud expose dashboard. Arqel integra.

**Descrição técnica**

Custom Pulse cards for Arqel:

- Total Resources
- Top actions by count
- AI tokens used
- Queue job metrics (Arqel-specific jobs)
- Slow query detection in Resource controllers

**Critérios de aceite**

- [ ] Pulse cards implementados
- [ ] Auto-register when Pulse installed
- [ ] Teste: Pulse dashboard mostra Arqel data

---

### [LCLOUD-004] One-click deploy flow

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** infra • **Depende de:** [LCLOUD-001]

**Contexto**

Button "Deploy to Laravel Cloud" que orchestra tudo.

**Descrição técnica**

Integration via GitHub OAuth + Laravel Cloud API:

1. User clica button em arqel.dev
2. Authorize GitHub + Laravel Cloud
3. Fork template
4. Create Laravel Cloud app pointing to fork
5. Configure env vars (auto)
6. Deploy
7. Redirect to running app

**Critérios de aceite**

- [ ] Button click → app running in <5 min
- [ ] Error handling robust
- [ ] Teste E2E: full flow com test accounts

---

### [LCLOUD-005] Docs + case study Laravel Cloud

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** M • **Camada:** docs • **Depende de:** [LCLOUD-004]

**Descrição técnica**

Docs:
- Deploy Arqel to Laravel Cloud guide
- Auto-scaling considerations
- Cost estimation calculator
- Comparison with other hosts (Fly.io, Render, DigitalOcean)

Case study: migration from Forge to Cloud com Arqel app.

**Critérios de aceite**

- [ ] Guide publicado
- [ ] Case study publicado

---

## 6. Headless PDF generator (PDF)

### [PDF-001] Esqueleto do pacote `arqel/pdf`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [EXPORT-001] (Fase 2)

**Contexto**

Cobre RF-EC-04. Fase 2 tem export PDF via dompdf. Fase 4 add headless PDF generator (Browsershot + Puppeteer) para PDFs complexos.

**Descrição técnica**

Estrutura `packages/pdf/`:

- `composer.json` (dep: `arqel/core`, suggest: spatie/browsershot)
- `src/PdfGenerator.php`
- `src/BrowsershotEngine.php`
- `src/TemplateRenderer.php`
- `src/Actions/GeneratePdfAction.php`
- SKILL.md, tests/

**Critérios de aceite**

- [ ] Pacote resolve
- [ ] SKILL.md esqueleto

---

### [PDF-002] Browsershot integration + template rendering

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [PDF-001]

**Descrição técnica**

Usa Spatie Browsershot (wrapper Puppeteer).

```php
use Spatie\Browsershot\Browsershot;

final class BrowsershotEngine
{
    public function generate(string $html, array $options = []): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'arqel-pdf-');
        
        Browsershot::html($html)
            ->format('A4')
            ->margins(...$options['margins'] ?? [10, 10, 10, 10])
            ->showBackground()
            ->waitUntilNetworkIdle()
            ->landscape($options['landscape'] ?? false)
            ->save($tmpPath);
        
        return $tmpPath;
    }
}
```

Template via Blade:

```php
$pdfPath = app(PdfGenerator::class)->fromView('pdfs.invoice', ['order' => $order]);
```

**Critérios de aceite**

- [ ] Generate PDF from HTML string
- [ ] Generate from Blade view
- [ ] Options: margins, format, landscape, header/footer
- [ ] Teste: generate invoice PDF, verify structure

**Notas de implementação**

- Puppeteer requires Chrome binary installed on server.
- Alternative: chromium-headless via container (easier deploy).

---

### [PDF-003] Action `GeneratePdfAction` para Resources

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [PDF-002]

**Descrição técnica**

```php
use Arqel\Pdf\Actions\GeneratePdfAction;

// In Resource table
Table::make()->actions([
    GeneratePdfAction::make('invoice')
        ->label('Generate Invoice')
        ->template('pdfs.invoice')
        ->filename(fn ($record) => "invoice-{$record->id}.pdf")
        ->queue(), // Async
]);
```

User provides template Blade; action generates + downloads OR queues.

**Critérios de aceite**

- [ ] Action integra com Resource
- [ ] Blade template data binding
- [ ] Download direct OR queued
- [ ] Teste: generate invoice, verify file

---

### [PDF-004] Multi-page reports + table of contents

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** L • **Camada:** php • **Depende de:** [PDF-002]

**Contexto**

Reports complexos: multi-page, TOC, headers/footers per page, page numbers.

**Descrição técnica**

API:

```php
PdfGenerator::make()
    ->coverPage('pdfs.cover', ['title' => 'Annual Report 2026'])
    ->toc()
    ->chapter('Introduction', 'pdfs.intro', compact('data'))
    ->chapter('Financial Results', 'pdfs.financials', compact('data'))
    ->chapter('Outlook', 'pdfs.outlook', compact('data'))
    ->header(function ($page) { /* render header */ })
    ->footer(function ($page) { /* page number */ })
    ->generate('annual-report-2026.pdf');
```

**Critérios de aceite**

- [ ] Multi-chapter PDFs funcionam
- [ ] TOC generated automatically with page numbers
- [ ] Headers/footers per page
- [ ] Teste: 50-page report com TOC correto

---

### [PDF-005] Testes + SKILL.md PDF

**Tipo:** test + docs • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php + docs • **Depende de:** [PDF-004]

**Descrição técnica**

- Tests generate PDFs, verify bytes
- Template rendering
- Multi-chapter logic
- SKILL.md: setup Browsershot, alternatives (dompdf for simple), performance considerations

**Critérios de aceite**

- [ ] Coverage ≥ 80%
- [ ] SKILL.md completo

---

## 7. Advanced i18n tooling (I18N)

### [I18N-001] Translation management UI

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** XL • **Camada:** php + react • **Depende de:** [CORE-013] (Fase 1)

**Contexto**

Cobre RF-EC-05. Managing translations via lang files is painful. UI admin para managing.

**Descrição técnica**

Arqel Resource `TranslationsResource`:

- Lista todas keys across lang files
- Grid: key × language
- Inline edit
- Missing translations highlighted (red)
- "Translate with AI" button per missing cell (usa AI field infrastructure from Fase 3)
- Export/import JSON
- Bulk operations

Writes back to `lang/{locale}/*.php` files (or database if preferred).

**Critérios de aceite**

- [ ] Grid shows keys × languages
- [ ] Inline edit saves to file
- [ ] AI translate funcional
- [ ] Import/export
- [ ] Teste: edit translation, verify file updated

---

### [I18N-002] Auto-extraction de strings para translate

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [I18N-001]

**Contexto**

`php artisan arqel:i18n:extract` scans code for `__('key')` e `trans('key')` calls, adiciona keys a lang files.

**Descrição técnica**

Parser AST de PHP files (via nikic/php-parser) + Blade + Vue/React (via regex for `useTranslate()('key')`).

Adds missing keys to lang files (preserving existing).

**Critérios de aceite**

- [ ] Extract detects __() calls
- [ ] Preserves existing values
- [ ] Adds missing keys em todos locales
- [ ] Teste: codebase com calls novos, verify extracted

---

### [I18N-003] Crowdin / Lokalise integration

**Tipo:** feat • **Prioridade:** P2 • **Estimativa:** L • **Camada:** php • **Depende de:** [I18N-001]

**Contexto**

Grandes orgs usam Crowdin/Lokalise. Arqel sync bidirectional.

**Descrição técnica**

Config:

```php
'i18n' => [
    'provider' => 'crowdin',
    'crowdin' => [
        'api_token' => env('CROWDIN_TOKEN'),
        'project_id' => env('CROWDIN_PROJECT_ID'),
    ],
],
```

Commands:
- `arqel:i18n:push` — upload lang files to Crowdin
- `arqel:i18n:pull` — download translations

**Critérios de aceite**

- [ ] Push/pull funcional
- [ ] Diff detection (só envia mudanças)
- [ ] Teste: end-to-end Crowdin flow

---

### [I18N-004] RTL support (arabic, hebrew)

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** L • **Camada:** react • **Depende de:** [UI-002] (Fase 1)

**Contexto**

Cobre RF-I-05. RTL languages precisam UI mirrored.

**Descrição técnica**

- CSS logical properties (start/end instead of left/right)
- HTML `dir="rtl"` when locale is RTL
- Components testados em RTL
- Icons ajustados (arrows flipped where appropriate)

**Critérios de aceite**

- [ ] Locale ar → UI mirrored
- [ ] Forms readable
- [ ] Tables correct direction
- [ ] Teste visual: Arabic interface

---

### [I18N-005] SKILL.md + docs i18n

**Tipo:** docs • **Prioridade:** P0 • **Estimativa:** M • **Camada:** docs • **Depende de:** [I18N-004]

**Descrição técnica**

SKILL.md + docs:
- Setup multi-language
- Translation UI usage
- Extraction workflow
- Crowdin integration
- RTL considerations

**Critérios de aceite**

- [ ] SKILL.md completo
- [ ] Docs publicadas

---

## 8. Certification program (CERT)

### [CERT-001] Arqel Certified Developer — curriculum

**Tipo:** docs + infra • **Prioridade:** P0 • **Estimativa:** XL • **Camada:** infra + docs • **Depende de:** [DOCS-V3-001] (Fase 3)

**Contexto**

Cobre RF-EC-06. Certification valida expertise + gera revenue + establece Arqel como skill valuable.

**Descrição técnica**

Curriculum (50-80 hours learning):

Module 1: Fundamentals (10h)
- Installation, concepts
- First Resource
- Panels

Module 2: Fields & Validation (10h)
- All field types
- Custom fields
- Validation strategies

Module 3: Tables & Forms (10h)
- Advanced tables
- Form layouts
- Dependent fields

Module 4: Actions & Policies (10h)
- Authorization patterns
- Actions
- Queued bulk operations

Module 5: Multi-tenancy (8h)
- Tenant strategies
- stancl integration

Module 6: Dashboards & Widgets (8h)
- Widget types
- Custom widgets
- Real-time

Module 7: Advanced Topics (14h)
- AI fields
- Workflow engine
- Versioning
- Semantic search
- MCP integration

**Critérios de aceite**

- [ ] Curriculum documented
- [ ] 80+ hours worth of material
- [ ] Practical exercises per module
- [ ] Docs site dedicated

---

### [CERT-002] Exam platform + practice tests

**Tipo:** infra • **Prioridade:** P0 • **Estimativa:** L • **Camada:** infra • **Depende de:** [CERT-001]

**Contexto**

Proctored online exam.

**Descrição técnica**

- Plataforma: partner com existing (Teachable, Thinkific) OR build in-house (Arqel-powered)
- Exam: 100 questions, 2 hours, closed-book
- Practical portion: build mini Arqel app passing test criteria
- Proctored: webcam monitoring (via 3rd-party service like Proctorio)
- Pass: 70%+

**Critérios de aceite**

- [ ] Exam platform live
- [ ] Practice tests disponíveis
- [ ] Proctoring funcional
- [ ] Auto-grading para questions, manual para practical

---

### [CERT-003] Certificate generation + verification

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [CERT-002]

**Descrição técnica**

Upon pass:
- PDF certificate with unique verification code
- Email with badge image
- LinkedIn-compatible credential badge
- Public verification URL: `arqel.dev/verify/{code}`

**Critérios de aceite**

- [ ] Certificate PDF generated
- [ ] Verification URL funcional
- [ ] LinkedIn badge integration
- [ ] Teste: pass exam, verify certificate issued

---

### [CERT-004] Certified developer directory

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [CERT-003]

**Contexto**

Employers querem contratar certified devs. Directory público.

**Descrição técnica**

Public page arqel.dev/certified-developers:

- Searchable list
- Filter by location, specialization
- Profile per dev: certs, expertise, hire-me status
- Companies podem post jobs targeting certified devs

**Critérios de aceite**

- [ ] Directory live
- [ ] Search + filters
- [ ] Dev profile page
- [ ] Opt-in (privacy)

---

## 9. Misc & community (MISC)

### [MISC-001] Arqel Showcase — apps built with Arqel

**Tipo:** infra + docs • **Prioridade:** P1 • **Estimativa:** M • **Camada:** infra + docs • **Depende de:** [DOCS-V3-002] (Fase 3)

**Contexto**

Showcase inspires + establishes credibility.

**Descrição técnica**

Página `arqel.dev/showcase`:

- Grid de apps usando Arqel
- Per app: screenshot, description, company, link
- Submission form via community

Fair (editors-curate) to avoid spam.

**Critérios de aceite**

- [ ] Showcase page live
- [ ] 20+ apps featured no launch
- [ ] Submission flow

---

### [MISC-002] Arqel Awards annual

**Tipo:** infra • **Prioridade:** P2 • **Estimativa:** M • **Camada:** infra • **Depende de:** [MISC-001]

**Descrição técnica**

Annual awards:
- Best Arqel App
- Best Plugin of the Year
- Most Impactful Contributor
- Rising Star (new developer)

Nominations community-driven. Judged by committee.

**Critérios de aceite**

- [ ] Awards launched in Q4 annually
- [ ] Voting/nomination platform
- [ ] Ceremony (at Laracon idealmente)

---

### [MISC-003] Arqel Conf (eventual)

**Tipo:** infra • **Prioridade:** P2 • **Estimativa:** XL • **Camada:** infra • **Depende de:** [MISC-002]

**Contexto**

Eventually: own conference OR dedicated track em Laracon.

**Descrição técnica**

Considerations:
- Budget (venue, speakers, catering) — heavy
- Alternative: 2-day virtual conference
- Start small: local meetups in major cities
- Scale to 1-day conference em year 5+

**Critérios de aceite**

- [ ] Initial meetups organized em 3+ cities
- [ ] Community appetite validated
- [ ] Conference/track evaluated

---

## 10. Ordem sugerida de execução

Fase 4 é naturally ongoing — não é strict linear. Todos tracks podem correr em paralelo consoante priorities negócio.

### Year 1 (months 1-12 após v1.0)

**Priorities:**

1. **MKTPLC-001 to 005** — core marketplace launch (Q1)
2. **DEVTOOLS-001 to 004** — devtools básicos (Q1-Q2)
3. **LCLOUD-001 to 005** — Laravel Cloud integration (Q1)
4. **CLI-TUI-001, 002, 004** — CLI improvements (Q2)
5. **PDF-001 to 005** — PDF generator (Q2-Q3)
6. **I18N-001, 002, 005** — i18n tooling (Q3)
7. **CERT-001** — cert curriculum (Q3-Q4)
8. **MKTPLC-006 to 010** — marketplace completion (Q3-Q4)

### Year 2 (months 13-24)

**Priorities:**

1. **MKTPLC-008** — premium plugins monetization
2. **DEVTOOLS-005 to 008** — devtools avançados
3. **CERT-002 to 004** — certification launch
4. **I18N-003, 004** — advanced i18n
5. **CLI-TUI-003** — Ink UI (optional)
6. **MISC-001 to 003** — community infrastructure

### Ongoing (year 3+)

- Iterative improvements across all tracks
- Community-driven features via marketplace
- Enterprise features based on customer feedback
- Potential expansion: mobile SDK, additional frontend stacks (unlikely but possible)

### Critérios de saída Fase 4 (ongoing — no hard finish)

**Targets 2 years post-v1.0:**

- 15.000+ GitHub stars
- >200 production SaaS públicos
- Sustentável economicamente (≥1 full-time maintainer paga)
- Plugin marketplace com ≥50 plugins
- Certification com ≥50 certified devs
- Laravel News featured multiple times
- Laracon talks delivered

---

## Resumo

**Fase 4 Ecossistema:** ~45 tickets detalhados, 12+ meses ongoing com 4-6 devs + community.

**Entregas principais:**
- DevTools browser extension com 4+ painéis (Inertia state, Policy debugger, Field inspector, Performance)
- CLI TUI avançado (arqel new, interactive generator, doctor, opcional Ink UI)
- Plugin marketplace completo (submission, reviews, premium, security scanning, install via CLI)
- Laravel Cloud integration oficial (template, one-click deploy, Pulse cards)
- Headless PDF generator (Browsershot + multi-page reports)
- Advanced i18n tooling (UI management, extraction, Crowdin, RTL)
- Certification program (curriculum, exam, certificates, directory)
- Community infrastructure (showcase, awards, meetups)

**Próximo documento (ÚLTIMO):** `12-processos-qa.md` — processos, CI/CD, QA, release, segurança, governança consolidados.
