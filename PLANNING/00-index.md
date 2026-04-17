# Arqel — Pacote de Planeamento Completo

> Planeamento completo para o desenvolvimento de **Arqel**, a framework open-source de admin panels para Laravel que combina declaração PHP-first (como Filament) com renderização React + ShadCN (como Nova, mas open-source).

## Visão resumida

**Arqel** é um package Laravel que permite:

```bash
composer require arqel/arqel
php artisan arqel:install
php artisan arqel:resource User
```

…e obter um admin panel completo, declarado em PHP idiomático, renderizado em React 19 + ShadCN UI via Inertia.js 3.

## Posicionamento no mercado

| | Filament v4 | Nova v5 | **Arqel** |
|---|---|---|---|
| Stack frontend | Blade + Livewire + Alpine | Inertia + Vue 3 | **Inertia + React 19 + ShadCN** |
| Licença | MIT (free) | Comercial (pago) | **MIT (free)** |
| Declaração | PHP classes | PHP classes | **PHP classes** |
| Type-safety E2E | Parcial (PHP + Blade) | Parcial (PHP + Vue props) | **Total (PHP + Zod + TypeScript)** |
| Customização UI | Blade templates + CSS | Vue components | **React components via ShadCN CLI** |
| AI-native | Filament Blueprint (parcial) | — | **SKILL.md + MCP server + AGENTS.md** |
| Multi-tenancy | Plugin oficial | Built-in | **Built-in + plugin ecosystem** |

## Índice dos documentos

| # | Documento | Propósito |
|---|---|---|
| **00** | `00-index.md` | Este arquivo — mapa de leitura e convenções |
| **01** | `01-spec-tecnica.md` | Especificação técnica completa (RF e RNF) |
| **02** | `02-arquitetura.md` | Arquitectura, diagramas C4, fluxos de dados |
| **03** | `03-adrs.md` | Architecture Decision Records |
| **04** | `04-repo-structure.md` | Estrutura do repositório, `composer.json`, configs |
| **05** | `05-api-php.md` | Contratos PHP (Resource, Field, Action classes) |
| **06** | `06-api-react.md` | Contratos TypeScript (React components, Inertia props) |
| **07** | `07-roadmap-fases.md` | Plano mestre das 4 fases |
| **08** | `08-fase-1-mvp.md` | Tickets detalhados Fase 1 |
| **09** | `09-fase-2-essenciais.md` | Tickets detalhados Fase 2 |
| **10** | `10-fase-3-avancadas.md` | Tickets detalhados Fase 3 |
| **11** | `11-fase-4-ecossistema.md` | Tickets detalhados Fase 4 |
| **12** | `12-processos-qa.md` | Processos, CI/CD, QA, release, segurança |

## Ordem de leitura recomendada

**Stakeholder / decisor:** `00` → `01` (seção 1) → `07` → `08` seção 1.

**Tech lead a planear fase:** `01` → `02` → `03` → `07` → fase específica → `12`.

**Dev Laravel a pegar primeiro ticket:** `01` (seção 1) → `04` → `05` → ticket concreto.

**Dev React a pegar primeiro ticket:** `01` (seção 1) → `04` → `06` → ticket concreto.

**Contributor externo:** `00` → `01` → ticket + `12` (processo de contribuição).

## Stack tecnológico definitivo

### Backend (PHP)

- **PHP 8.3+** (recomendado 8.4)
- **Laravel 12+** (13 quando estável)
- **Inertia.js 3** via `inertiajs/inertia-laravel`
- **Eloquent** como ORM
- **Pest 3+** para testes (PHPUnit compatível)
- **Orchestra Testbench** para testes de package
- **Spatie packages** integrados opcionalmente: Permission, Media Library, Activity Log, Backup
- **Laravel Reverb** para WebSockets (Fase 3+)
- **Laravel Pint** para lint/format

### Frontend (React)

- **React 19.2.3+** (CVE-patched)
- **TypeScript 5.5+** (strict mode)
- **Inertia.js 3 React adapter** (`@inertiajs/react`)
- **Tailwind CSS v4**
- **ShadCN CLI v4** (Base UI default)
- **TanStack Table v8** (v9 quando estável)
- **TanStack Form** para forms avançados
- **Zod 4** para validação cliente (espelha Laravel rules)
- **Vite 7** como bundler
- **Vitest + Testing Library** para unit tests
- **Playwright 1.59+** para E2E

### Distribuição

- **Packagist:** `arqel/arqel` (principal), `arqel/core`, `arqel/ui`, `arqel/fields`, `arqel/table`, `arqel/form`, `arqel/actions`, etc.
- **npm:** `@arqel/ui`, `@arqel/fields`, `@arqel/table`, `@arqel/hooks`, `@arqel/types` (componentes React e types)
- **Domínio principal:** `arqel.dev`
- **GitHub org:** `github.com/arqel`

## Convenções em todos os documentos

### Formato de tickets

```
### [PACKAGE-###] Título curto e acionável

**Tipo:** feat | chore | docs | test | refactor | infra
**Prioridade:** P0 (blocker) | P1 (crítico) | P2 (normal) | P3 (nice-to-have)
**Estimativa:** XS (<2h) | S (2-8h) | M (1-3d) | L (3-7d) | XL (>1 semana)
**Camada:** php | react | shared | infra | docs
**Labels:** `package:core`, `phase:1`, `area:fields`, etc.
**Depende de:** [TICKET-###]
**Bloqueia:** [TICKET-###]

**Contexto**
Parágrafo explicando o porquê.

**Descrição técnica**
Detalhes da implementação.

**Critérios de aceite**
- [ ] Afirmação verificável 1
- [ ] Afirmação verificável 2

**Notas de implementação**
Avisos, armadilhas, dicas.
```

### Convenção de IDs

**PHP packages (Packagist):**
- `CORE` — `arqel/core` (traits, contracts, service provider)
- `PANEL` — `arqel/arqel` (meta-package, instalação)
- `FIELDS` — `arqel/fields`
- `TABLE` — `arqel/table`
- `FORM` — `arqel/form`
- `ACTIONS` — `arqel/actions`
- `AUTH` — `arqel/auth` (authorization, policies)
- `NAV` — `arqel/nav` (navegação, menu)
- `TENANT` — `arqel/tenant` (multi-tenancy)
- `AUDIT` — `arqel/audit`
- `VERS` — `arqel/versioning`
- `WF` — `arqel/workflow`
- `RT` — `arqel/realtime`
- `CLI` — comandos Artisan
- `MCP` — `arqel/mcp` (MCP server)

**npm packages:**
- `REACT` — `@arqel/react` (bindings Inertia + hooks)
- `UI` — `@arqel/ui` (componentes visuais ShadCN-based)
- `TYPES` — `@arqel/types` (TypeScript types espelhando PHP)

**Transversais:**
- `INFRA` — CI/CD, repo config
- `GOV` — governança, licença, CoC
- `DOCS` — documentação
- `DEMO` — apps de exemplo
- `QA` — testing infra

### Prioridades e estimativas

Iguais ao planeamento anterior (ver seção "Tone and formatting" de qualquer documento Arqel).

## Decisões arquiteturais fundamentais (ver `03-adrs.md`)

1. **Inertia.js 3** como única bridge PHP↔React
2. **Laravel-only** (zero multi-PHP framework support)
3. **Inertia props** como default para state/data (sem TanStack Query default)
4. **Eloquent** como ORM suportado (sem DB-agnosticism)
5. **ShadCN CLI v4** para distribuição de componentes React (user owns the code)
6. **Base UI default**, Radix opt-in
7. **Pest 3** como test runner (PHPUnit compatível mas sintaxe moderna)
8. **Monorepo Composer + npm** num único repo
9. **MIT License + DCO**
10. **SemVer** rigoroso a partir de 1.0
11. **Laravel 12+ mínimo** (não suportamos 10/11)
12. **PHP 8.3+ mínimo** (readonly classes, typed properties, etc.)
13. **MCP server** oficial desde Fase 2
14. **Filament-compatible patterns** onde fizer sentido (reduzir fricção de migração)

## Totais estimados

| Fase | Duração | Tickets | Pessoas (team) |
|---|---|---|---|
| Fase 1 (MVP) | 4-7 meses | ~120 tickets | 2-3 devs (1 senior Laravel + 1 React + 1 full-stack) |
| Fase 2 (Essenciais) | 4-7 meses | ~90 tickets | 3-4 devs |
| Fase 3 (Avançadas) | 7-10 meses | ~70 tickets | 3-5 devs |
| Fase 4 (Ecossistema) | 12+ meses | ~45 tickets | 4-6 devs + community |
| **Total** | **27-36 meses** | **~325 tickets** | — |

**Nota:** estimativas 10-15% mais altas que no planeamento anterior porque temos duas stacks (PHP + React) em vez de uma só React.

## Convenções de nomenclatura

- **Documentação:** português de Portugal.
- **Código PHP, classes, namespaces:** inglês, seguir PSR-12.
- **Código TypeScript, components, hooks:** inglês, camelCase/PascalCase standard.
- **Commits:** Conventional Commits (`feat:`, `fix:`, etc.).
- **Branches:** `main`, `develop`, `feat/<ticket-id>-<slug>`, `fix/<ticket-id>-<slug>`.

## Fora do âmbito deste pacote

1. **Go-to-market e pricing** — decisão de negócio separada.
2. **Contratação detalhada** — perfis sugeridos mas não tratados.
3. **Suporte a Symfony / outros PHP frameworks** — Laravel-only por design.
4. **Suporte a Vue / Svelte** — React-only por design.
5. **Substituir Filament no nicho Livewire-first** — coexistimos, não substituímos.
6. **Hospedar um SaaS Arqel** — somos framework, não plataforma.

## Próximo passo

Ler `01-spec-tecnica.md` para requisitos completos, depois `07-roadmap-fases.md` para o mapa temporal, depois mergulhar nas fases.
