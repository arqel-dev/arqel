# 03 — Architecture Decision Records (ADRs)

> Registo formal das decisões arquiteturais do projeto Arqel. Cada ADR documenta **contexto, decisão, consequências** — para que futuros contributors percebam o porquê, não apenas o quê.

## Índice

| ADR | Título | Data | Estado |
|---|---|---|---|
| ADR-001 | Inertia.js como bridge única PHP↔React | 2026-04 | Aceite |
| ADR-002 | Laravel-only (zero multi-framework) | 2026-04 | Aceite |
| ADR-003 | Eloquent-native (não DB-agnostic) | 2026-04 | Aceite |
| ADR-004 | React 19.2+ obrigatório | 2026-04 | Aceite |
| ADR-005 | Rejeitar Livewire | 2026-04 | Aceite |
| ADR-006 | ShadCN CLI v4 para distribuição componentes | 2026-04 | Aceite |
| ADR-007 | Base UI como default, Radix opt-in | 2026-04 | Aceite (Update 2026-05: migrated to Radix via shadcn CLI v4 — see CHANGELOG) |
| ADR-008 | Pest 3 como test runner | 2026-04 | Aceite |
| ADR-009 | Monorepo Composer + npm | 2026-04 | Aceite |
| ADR-010 | MIT License + DCO | 2026-04 | Aceite |
| ADR-011 | PHP 8.3+ mínimo | 2026-04 | Aceite |
| ADR-012 | Laravel 12+ mínimo | 2026-04 | Aceite |
| ADR-013 | MCP server oficial desde Fase 2 | 2026-04 | Aceite |
| ADR-014 | Filament-compatible patterns onde fizer sentido | 2026-04 | Aceite |
| ADR-015 | Spatie packages como integrações opcionais | 2026-04 | Aceite |
| ADR-016 | Inertia props como default state, sem TanStack Query | 2026-04 | Aceite |
| ADR-017 | Laravel Policies como authorization canónica | 2026-04 | Aceite |
| ADR-018 | Service Provider com auto-discovery | 2026-04 | Aceite |

---

## ADR-001: Inertia.js como bridge única PHP↔React

**Estado:** Aceite • **Data:** 2026-04

### Contexto

Arqel precisa de uma forma de comunicação entre Laravel (PHP) e React. As opções consideradas:

1. **API REST separada** — Laravel expõe `/api/*`, React SPA consume via fetch + TanStack Query.
2. **GraphQL** — Lighthouse ou similar, React consume via Apollo/urql.
3. **Livewire + React hybrid** — Livewire gera HTML, React inserido em spots específicos.
4. **Inertia.js** — monolith pattern, Laravel controllers retornam Inertia responses com React components.

### Decisão

**Inertia.js v3 é a única bridge suportada.** Sem fallback para API REST. Sem GraphQL. Sem Livewire.

### Rationale

- **Validação empírica:** Nova v5 usa Inertia (com Vue, mas o padrão é idêntico). Demonstra que funciona para admin panels de escala enterprise.
- **Elimina duplicação:** Laravel validation rules são a única source of truth. Client-side Zod é espelho derivado.
- **Type-safety E2E viável:** PHP Resource → Inertia props → TypeScript types. Difícil com REST puro, impossível com GraphQL sem extra tooling.
- **Bundle size:** Inertia 3 abandonou Axios, usa XHR nativo. <30KB gzipped total.
- **DX Laravel-friendly:** controllers normais, routes normais, middleware normal. Curva de aprendizagem mínima.
- **Backing oficial:** Inertia foi adoptada pelo Laravel team (blog posts oficiais, starter kits).

### Consequências

**Positivas:**
- Zero duplicação validation
- Monólito simples de deploy
- Developers Laravel sentem-se em casa
- Partial reloads, deferred props, optimistic updates nativos

**Negativas:**
- Não suporta mobile native apps diretamente (precisariam de API REST separada)
- Lock-in à Inertia (se o projeto stagnar, temos problema — mitigado por Laravel backing)
- Não é padrão em outros ecossistemas (React devs sem Laravel vão achar estranho ao início)

### Alternativas rejeitadas

- **API REST:** duplicação de validation, auth boundary mais complexa, bundle maior.
- **GraphQL:** overkill para admin panels, extra tooling obrigatório.
- **Livewire + React hybrid:** complexidade catastrófica, testing impossível, dois mental models em simultâneo.

---

## ADR-002: Laravel-only (zero multi-framework)

**Estado:** Aceite • **Data:** 2026-04

### Contexto

Poderíamos suportar múltiplos PHP frameworks (Symfony, Laminas, CakePHP) para ampliar target audience.

### Decisão

**Laravel é a única stack backend suportada.** Versões 12+ (13+ quando estável).

### Rationale

- **Validação de mercado:** Filament (Laravel-only) tem 20k+ stars. Nova (Laravel-only) é comercial viável.
- **Eloquent é idiomático:** abstrair sobre múltiplos ORMs mata 80% das conveniences (scopes, casts, relationships).
- **Laravel domina PHP moderno:** >50% market share em 2026.
- **Foco = velocidade de execução:** multi-framework triplica effort de testing, docs, manutenção.
- **Inertia-Laravel adapter é maduro** (v3.0.6, 53M installs). Outros frameworks têm adapters mas menos maturos.

### Consequências

**Positivas:**
- 100% foco em DX Laravel
- Integrações profundas (Policies, Eloquent, Artisan, Octane)
- Tickets menores, testing mais rápido

**Negativas:**
- Ignoramos ~30% do mercado PHP (Symfony devs)
- Nome do projeto implica Laravel (OK — é propositado)

### Alternativas rejeitadas

- **Multi-framework desde dia 1:** effort exponencial, sem validação de demand Symfony.
- **Core framework-agnostic + adapters:** adicionamos complexidade interna sem ganho real (ver Filament que também rejeitou).

---

## ADR-003: Eloquent-native (não DB-agnostic)

**Estado:** Aceite • **Data:** 2026-04

### Contexto

Poderíamos abstrair data access via Repository Pattern ou custom query builder.

### Decisão

**Resources dependem de Eloquent models diretamente.** Sem Repository Pattern. Sem query builder abstraction. Eloquent é a fonte de verdade.

### Rationale

- Eloquent é o ORM canónico Laravel.
- **Global scopes** (usados em multi-tenancy) são Eloquent-native.
- **Relationships** (belongsTo, hasMany, morphTo) são centrais em admin panels.
- **Casts, accessors, mutators** são conveniences que Repository Pattern esconde.
- Filament prova que Eloquent-native funciona em scale.

### Consequências

**Positivas:**
- API super simples: `Resource::$model = User::class`
- Performance otimizada (eager loading auto-detectado)
- Menos indirection

**Negativas:**
- Não suportamos Eloquent-alternatives (ex: DB::table() queries directas)
- Users que queiram ORM diferente não podem usar Arqel (OK — target é Laravel)

### Alternativas rejeitadas

- **Repository Pattern:** adiciona layer sem ganho prático para admin panels.
- **Custom query builder:** reinventa roda, perde Eloquent features.

---

## ADR-004: React 19.2+ obrigatório

**Estado:** Aceite • **Data:** 2026-04

### Contexto

React 19.0 saiu em Dez 2024. React 19.2 saiu em Março 2026 com `<Activity>` component, Compiler estável, e patches CVE-2025-55182.

### Decisão

**React 19.2.3 é o mínimo obrigatório.** Sem suporte a React 18.x.

### Rationale

- **CVE-2025-55182** em React 19.1 e anteriores exige upgrade.
- **React 19.2 Compiler** elimina necessidade de `useMemo`/`useCallback` manual — código mais limpo.
- **`<Activity>` component** habilita preservação de state em stacked modals (Actions Fase 2).
- **Inertia v3 React adapter exige React 19+** — alinhado com nossa bridge.
- **2026 é ano de migração:** a maioria dos projetos React sérios está migrando; não vale a pena criar package que nasce legacy.

### Consequências

**Positivas:**
- Código moderno, performance otimizada por Compiler
- Security baseline garantida
- Features avançadas (Activity, useOptimistic, useActionState) disponíveis

**Negativas:**
- Users em React 18 precisam upgrade antes de Arqel
- Terceiros packages React que ainda não suportam React 19 podem não funcionar (raro em 2026)

### Alternativas rejeitadas

- **React 18 support:** deixa-nos vulneráveis a CVE, sem Compiler, sem Activity.
- **Preact:** pequena, rápida, mas sem suspense/Inertia React adapter, ecossistema menor.

---

## ADR-005: Rejeitar Livewire

**Estado:** Aceite • **Data:** 2026-04

### Contexto

Livewire é a stack oficial Filament. Alternativa legítima para frontend Laravel.

### Decisão

**Arqel não usa Livewire.** Stack frontend é React via Inertia.

### Rationale

- **Segmentação clara:** Filament ocupa o nicho Livewire-first. Não competimos aí.
- **DX diferente:** devs que querem React não querem Livewire (e vice-versa).
- **Limites do Livewire:** hydration overhead, customização UI complexa, bundle size por página.
- **React ecosystem >> Livewire ecosystem** em número de bibliotecas UI, charts, forms, etc.
- **Type-safety:** Livewire tem types PHP mas fronteira render é stringly-typed; React + TypeScript dá type-safety E2E.

### Consequências

**Positivas:**
- Diferenciação clara vs Filament
- Ecossistema React disponível
- Performance previsível

**Negativas:**
- Users Filament-first não adoptam
- Dois builds (PHP + JS) vs um só

### Alternativas rejeitadas

- **Livewire como primary:** idêntico a Filament, sem diferenciador.
- **Livewire + React hybrid:** complexidade catastrófica.

---

## ADR-006: ShadCN CLI v4 para distribuição componentes

**Estado:** Aceite • **Data:** 2026-04

### Contexto

Como distribuir componentes UI (Button, Input, Select, etc.):

1. **npm package tradicional** — `import { Button } from '@arqel-dev/ui'`, versionado.
2. **ShadCN CLI pattern** — user executa `npx shadcn@latest add arqel-dev/button`, componente é copiado para `resources/js/components/ui/button.tsx`, versionado pelo user.
3. **Hybrid** — structural via npm, atómicos via ShadCN CLI.

### Decisão

**Hybrid com ShadCN CLI v4 para primitives + npm para structural.**

- **@arqel-dev/ui (npm):** AppShell, Sidebar, Topbar, DataTable shell, FormRenderer — componentes estruturais que mudam raramente.
- **arqel.dev/r/** (ShadCN CLI registry): Button, Input, Select, Dialog, etc. — componentes atómicos, user owns.

### Rationale

- **User owns the code:** customização sem forks, sem CSS overrides hacky.
- **ShadCN é o padrão 2026:** toda a comunidade React moderna conhece.
- **Radix compatibility:** shadcn CLI v4 (new-york) usa Radix UI (tema que escolhemos em ADR-007, atualizado 2026-05).
- **Structural via npm** porque updates de AppShell não devem quebrar apps (semver strict).

### Consequências

**Positivas:**
- DX superior (componentes editáveis)
- Sem CSS fighting
- Theme customization natural

**Negativas:**
- Two mental models (npm vs CLI) — mitigado por docs claras
- Users precisam ShadCN CLI instalado (já padrão em 2026)

### Alternativas rejeitadas

- **Tudo via npm:** mata customização, forces users a fight CSS.
- **Tudo via CLI:** AppShell não deve ser copy-paste (muda com versões Arqel).

---

## ADR-007: Base UI como default, Radix opt-in

**Estado:** Aceite • **Data:** 2026-04 • **Update 2026-05:** migrated to Radix via shadcn CLI v4 (new-york) — ver CHANGELOG. Decisão original abaixo preservada para histórico.

### Contexto

Base UI (2025+) é o sucessor moderno do Radix UI para primitives headless. ShadCN CLI v4 tornou-se Base UI-first.

### Decisão

**Base UI é o primitive layer default.** Radix opt-in via `@arqel-dev/ui-radix` package.

### Rationale

- **ShadCN CLI v4 ships com Base UI** — alinhamento com ecossistema.
- **Base UI tem engenharia full-time** (ex-Radix team) — manutenção garantida.
- **Radix ainda tem mais tracking** (~11M weekly downloads); podemos não perder users com opt-in.
- **Base UI tem melhor TypeScript, SSR, e a11y** vs Radix clássico.

### Consequências

**Positivas:**
- Alinhamento com ShadCN CLI v4 default
- Access à inovação Base UI team
- A11y superior

**Negativas:**
- Ecossistema Radix maior em número de users (transição)
- Third-party packages Radix-based precisam compatibility layer

### Alternativas rejeitadas

- **Radix default:** força users a mudar depois.
- **HeadlessUI:** sem React 19 support garantido.

---

## ADR-008: Pest 3 como test runner

**Estado:** Aceite • **Data:** 2026-04

### Contexto

PHPUnit é o test runner histórico Laravel. Pest é alternative moderna.

### Decisão

**Pest 3 é o test runner canónico em docs e examples.** PHPUnit 11+ continua suportado (Pest é compatible).

### Rationale

- **Pest 3 é o default em `laravel new`** desde Laravel 11+.
- **Syntax expressiva:** `it('creates user', ...)` vs `public function test_creates_user(): void`.
- **Plugins maduros:** Pest Stressless, Pest Architect, Pest Mutation Testing.
- **Community:** maioria dos packages modernos Laravel usam Pest.

### Consequências

**Positivas:**
- Docs modernas, exemplos limpos
- Mutation testing disponível
- Stress testing para performance gates

**Negativas:**
- Users em PHPUnit tradicional podem ter fricção (mas docs cobrem ambos)

### Alternativas rejeitadas

- **PHPUnit only:** verboso, desalinhado com ecossistema moderno.

---

## ADR-009: Monorepo Composer + npm

**Estado:** Aceite • **Data:** 2026-04

### Contexto

Ter um único Git repo para todos os packages (PHP + JS) vs múltiplos repos.

### Decisão

**Monorepo único em `github.com/arqel-dev/arqel`** contendo:

- `packages/` — PHP packages Composer
- `packages-js/` — npm packages
- `apps/playground/` — demo app
- `apps/docs/` — Nextra docs site

### Rationale

- **Precedente:** Laravel framework (monorepo), Filament (monorepo), React ecosystem monorepos.
- **Releases coordenados:** PR pode tocar PHP + TS types simultaneamente.
- **Single issue tracker, docs, CI.**
- **Simpler onboarding** para contributors.

### Consequências

**Positivas:**
- Mudanças atómicas cross-stack
- Docs centralizadas
- CI único

**Negativas:**
- Repo grande (mitigado por pnpm workspaces + Composer path repositories)
- Possivel slow clone (shallow clones)

### Alternativas rejeitadas

- **Multi-repo:** releases dessincronizados, ferramenta extra (semantic-release, changesets) necessária.

---

## ADR-010: MIT License + DCO

**Estado:** Aceite • **Data:** 2026-04

### Contexto

Escolha de licença e mecanismo de contribuição.

### Decisão

- **Licença:** MIT.
- **Contribuições:** DCO (Developer Certificate of Origin) — sem CLA.

### Rationale

- **MIT** — padrão Laravel ecosystem, máxima permissividade.
- **DCO > CLA** — menos fricção para contributors (git `--signoff`), sem transferência de copyright.

### Consequências

**Positivas:**
- Máxima adoção (commercial use sem restrição)
- Contribuições fáceis

**Negativas:**
- Fork-and-commercialize é legal (OK — modelo Laravel, jQuery, React)

### Alternativas rejeitadas

- **GPL:** copyleft barra adoção comercial.
- **Apache 2.0:** OK mas MIT é mais curto e idiomático Laravel.
- **BSL / Elastic License:** barra self-hosting, complexidade.

---

## ADR-011: PHP 8.3+ mínimo

**Estado:** Aceite • **Data:** 2026-04

### Contexto

PHP 8.4 estável, PHP 8.5 em Dez 2026. Laravel 12+ exige PHP 8.2+.

### Decisão

**PHP 8.3 é o mínimo absoluto.** Recomendado 8.4.

### Rationale

- **Readonly classes** (PHP 8.3) simplificam DTOs.
- **Typed class constants** (8.3) melhoram type-safety.
- **`json_validate()`** (8.3) útil em fields JSON.
- **Laravel 13 vai exigir 8.3+** (provável).
- **2026 tem >80% servidores em PHP 8.2+** (surveys Laravel News).

### Consequências

**Positivas:**
- Código moderno, type-safe
- Features avançadas disponíveis

**Negativas:**
- Users em PHP 8.2 precisam upgrade

### Alternativas rejeitadas

- **PHP 8.2 support:** limita features disponíveis, não justifica complexity.

---

## ADR-012: Laravel 12+ mínimo

**Estado:** Aceite • **Data:** 2026-04

### Contexto

Laravel 11 (Mar 2024), 12 (Feb 2025), 13 (previsto 2026).

### Decisão

**Laravel 12 é o mínimo.** Laravel 13 quando estável.

### Rationale

- **Laravel 12 é near-maintenance release** — upgrade de 11→12 trivial.
- **Laravel 11 LTS support** até Mar 2026 — já fora.
- **Inertia v3 oficial Laravel 11+** — nosso mínimo efectivo.
- **React starter kit oficial** em Laravel 12.

### Consequências

**Positivas:**
- Stack moderna
- Breeze React starter disponível

**Negativas:**
- Laravel 10/11 users precisam upgrade

### Alternativas rejeitadas

- **Laravel 11 support:** ciclo de support já terminou.

---

## ADR-013: MCP server oficial desde Fase 2

**Estado:** Aceite • **Data:** 2026-04

### Contexto

Model Context Protocol (MCP) está crescerndo como standard 2025/2026 para LLM tooling. Claude Code, Cursor, Windsurf, VS Code Copilot suportam.

### Decisão

**`arqel-dev/mcp` é package Composer oficial shipped em Fase 2.**

### Rationale

- **Diferenciador forte vs Filament/Nova** (nenhum tem MCP oficial).
- **AI-native strategy** — developers usam LLMs para code generation; MCP dá LLMs acesso estruturado ao nosso projeto.
- **Introspecção:** MCP permite listar Resources, Fields, Actions via tools.
- **Codegen:** MCP permite LLMs gerarem Resources corretos (contra alucinação).

### Consequências

**Positivas:**
- Claude Code, Cursor geram código Arqel-correto
- Positioning como AI-native framework

**Negativas:**
- Extra package para manter
- Standard MCP ainda evoluindo (risco pequeno)

### Alternativas rejeitadas

- **Sem MCP:** perdemos diferenciador-chave.

---

## ADR-014: Filament-compatible patterns onde fizer sentido

**Estado:** Aceite • **Data:** 2026-04

### Contexto

Developers Laravel familiarizados com Filament têm padrões aprendidos. Tornar migration fácil aumenta adoção.

### Decisão

**APIs Arqel seguem conventions Filament onde isso não conflitua com decisões de design.**

Exemplos:
- `Field::text('name')->required()` — idêntico a Filament
- Resource `schema()` method — idêntico
- Action classes com `->requiresConfirmation()` — idêntico
- Navigation items — idêntico

Onde divergimos (inevitável):
- Rendering (React vs Blade)
- State management (Inertia props vs Livewire)
- Component registration (React registry vs Blade components)

### Rationale

- **Cognitive load reduzida** para migrators.
- **Migration tools possíveis** (ex: Arqel Filament Compat layer — Fase 4).
- **Não reinventamos roda** onde Filament acertou (fluent API, lifecycle hooks).

### Consequências

**Positivas:**
- Onboarding Filament devs rápido
- Docs podem referenciar Filament docs para similares

**Negativas:**
- Algumas escolhas sub-ótimas herdadas (mitigado por divergir onde for claramente melhor)

### Alternativas rejeitadas

- **Nova API own** from scratch: força re-learning.

---

## ADR-015: Spatie packages como integrações opcionais

**Estado:** Aceite • **Data:** 2026-04

### Contexto

Spatie publica packages Laravel de fato-standard: Permission, Media Library, Activity Log, Model States, Backup.

### Decisão

**Spatie packages são integrações opcionais, não hard dependencies.**

- `arqel-dev/auth-spatie` — opt-in wrapper `spatie/laravel-permission`
- `arqel-dev/media-spatie` — opt-in wrapper `spatie/laravel-medialibrary`
- `arqel-dev/audit` — wraps `spatie/laravel-activitylog` (wrapper fino)
- `arqel-dev/workflow` — wraps `spatie/laravel-model-states`

### Rationale

- **Ecosystem leverage:** não reinventamos RBAC, activity log, etc.
- **Users que não querem Spatie** podem usar alternativas (custom auth, custom audit).
- **Hard dependency seria anti-pattern** (forces users a carregar packages que não precisam).

### Consequências

**Positivas:**
- Power users ligam a integrações Spatie
- Users minimalistas não carregam código extra
- Aproveitamos maturidade Spatie

**Negativas:**
- Mais packages para documentar
- Versioning coordination (Spatie releases major, temos de atualizar)

### Alternativas rejeitadas

- **Hard dependency em Spatie Permission:** force users a instalar mesmo se não precisarem.
- **Reimplementar RBAC próprio:** reinventar roda madura.

---

## ADR-016: Inertia props como default state, sem TanStack Query

**Estado:** Aceite • **Data:** 2026-04

### Contexto

React apps modernos usam TanStack Query (ou SWR, ou RTK Query) para data fetching + caching.

### Decisão

**Inertia props é o default state/data mechanism.** TanStack Query NÃO é dependency nem incluído por default.

### Rationale

- **Inertia 3** tem partial reloads, deferred props, optimistic updates nativos — cobre 90% dos cenários.
- **Simplicidade:** um mental model (Inertia) vs dois (Inertia + TanStack Query).
- **Bundle size:** TanStack Query adiciona ~40KB gzipped.
- **Sem cache complexity:** Inertia reload é explicit, previsível.

### Escape hatches:

- Users podem adicionar TanStack Query manualmente para casos avançados.
- `useHttp` hook do Inertia 3 cobre requests fora do ciclo Inertia.

### Consequências

**Positivas:**
- Bundle menor
- Mental model único
- DX consistente com Laravel patterns

**Negativas:**
- Infinite scroll avançado (background refetch, window-focus refetch) precisa tooling extra
- Optimistic updates complexos podem beneficiar de TanStack Query (mitigado por Inertia 3 `useOptimistic`)

### Alternativas rejeitadas

- **TanStack Query default:** adds complexity sem ganho claro para admin panels.
- **SWR:** mesmo argumento.

---

## ADR-017: Laravel Policies como authorization canónica

**Estado:** Aceite • **Data:** 2026-04

### Contexto

Authorization em admin panels é crítico. Opções:

1. **Custom ability system** (próprio)
2. **Laravel Policies** (standard)
3. **Laravel Gates** (mais simples, sem classes)
4. **Spatie Permission RBAC** (roles + permissions in DB)

### Decisão

**Laravel Policies é o mechanism canónico.** Gates suportados para casos simples. Spatie Permission é integração opcional.

### Rationale

- **Policies são o standard Laravel** desde L5.
- **Testable:** `actingAs($user)->get(...)` + `assertForbidden()`.
- **IDE-friendly:** classes dedicadas, methods tipados.
- **Escalável:** per-record, per-field, per-action.

### Consequências

**Positivas:**
- Familiar a qualquer dev Laravel
- Policy discovery automático via Laravel convention

**Negativas:**
- Users sem Policies existentes precisam escrever (mitigado por generator: `arqel:resource --with-policy`)

### Alternativas rejeitadas

- **Custom ability system:** reinventa roda.
- **Gates-only:** não escala para per-field auth.

---

## ADR-018: Service Provider com auto-discovery

**Estado:** Aceite • **Data:** 2026-04

### Contexto

Como registar Arqel numa Laravel app.

### Decisão

**`Arqel\ArqelServiceProvider` auto-discovered via `extra.laravel.providers` no `composer.json`.**

```json
"extra": {
    "laravel": {
        "providers": [
            "Arqel\\ArqelServiceProvider"
        ]
    }
}
```

### Rationale

- **Zero-config install:** `composer require arqel-dev/framework` + `php artisan arqel:install` → funciona.
- **Padrão Laravel package development.**
- **Opt-out possível** via `extra.laravel.dont-discover`.

### Consequências

**Positivas:**
- DX superior
- Sem manual registration

**Negativas:**
- Boot overhead mínimo mesmo se Arqel não for usado (mitigado — only registers quando routes match)

### Alternativas rejeitadas

- **Manual provider registration:** DX inferior.

---

## Processo de adicionar novos ADRs

Quando uma decisão arquitectural significativa é tomada:

1. Novo arquivo `/docs/adrs/ADR-XXX-titulo.md` seguindo template acima.
2. Discussão em PR antes de merge.
3. Estado inicial: `Proposto`. Transita para `Aceite`, `Rejeitado`, ou `Superseded por ADR-YYY`.
4. ADRs nunca são apagados — histórico de decisões preservado.
