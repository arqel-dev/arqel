# 12 — Processos, QA, CI/CD e Release

> Documento consolidado de processos operacionais do Arqel: CI/CD, testes, release management, segurança, governança, contribuição e deprecation policy. Complementa tickets INFRA-*, GOV-* dispersos nos docs de fase.

## Índice

1. [Visão geral](#1-visão-geral)
2. [Estratégia de testes](#2-estratégia-de-testes)
3. [CI/CD pipeline](#3-cicd-pipeline)
4. [Release management](#4-release-management)
5. [Versionamento e deprecation](#5-versionamento-e-deprecation)
6. [Segurança](#6-segurança)
7. [Governança e contribuição](#7-governança-e-contribuição)
8. [Code review e PR process](#8-code-review-e-pr-process)
9. [Qualidade de código](#9-qualidade-de-código)
10. [Documentação e SKILL.md](#10-documentação-e-skillmd)
11. [Monitoring e observabilidade](#11-monitoring-e-observabilidade)
12. [Incident response](#12-incident-response)

## 1. Visão geral

Este documento define *como* trabalhamos — as práticas, pipelines e políticas que mantêm Arqel consistente, seguro e sustentável ao longo do tempo. Os tickets nos docs 08-11 definem *o que* fazer; este doc define *como*.

**Princípios operacionais:**

- **Automação por default** — processos manuais convidam a erros e não escalam com contributors.
- **Tests first, always** — PRs sem testes são rejeitados; coverage é pré-requisito, não after-thought.
- **Visibilidade total** — builds, releases, bugs e decisões públicas. Transparência atrai contributors.
- **Semver rigoroso** — breaking changes em major versions only; deprecation period mínimo de 1 minor.
- **Security first** — vulnerabilities triadas em 48h, críticas corrigidas em 14 dias.
- **LTS comprometido** — v1.0 LTS garante stability para produção.

## 2. Estratégia de testes

### 2.1 Pirâmide de testes

Arqel segue a pirâmide clássica (ADR-008):

```
        /\
       /  \    E2E (Playwright) - ~5%
      /____\
     /      \   Feature/Integration (Pest + Testing Library) - ~30%
    /________\
   /          \   Unit (Pest + Vitest) - ~65%
  /____________\
```

**Unit tests (PHP + JS):**
- Testam funções/classes isoladas, sem IO
- Mocks para dependências externas
- Fast: <5ms per test
- Alvo: cada classe pública testável

**Feature tests (PHP):**
- Laravel HTTP tests: full request → response
- DB real (SQLite in-memory para CI)
- Setup: factories Eloquent
- Alvo: happy path + 3-5 error paths por endpoint

**Integration tests (JS):**
- React Testing Library para components
- Testam interações user-level (click, type, navigate)
- Mock fetch/Inertia router
- Alvo: cada component público com behavior complexo

**E2E tests (Playwright):**
- Full stack: Laravel rodando + browser real
- Smoke tests críticos: login, create Resource, full CRUD, logout
- CI-only (slow, ~30s per test)
- Alvo: 10-20 journeys cobrindo features core

### 2.2 Coverage targets

| Escopo | Target mínimo | Target ideal |
|---|---|---|
| Core packages PHP (core, fields, table, form, actions, auth, nav) | **90%** | 95% |
| Core packages JS (types, react, hooks, ui, fields) | **80%** | 90% |
| Extension packages (tenant, widgets, fields-advanced, mcp, realtime, ai) | **85%** | 90% |
| Ecosystem packages (export, audit, pdf, devtools) | **80%** | 85% |
| UI-heavy packages (ui com muito visual branching) | **75%** | 80% |

Coverage gate **blocks merge** se abaixo do target. Exceptions require maintainer sign-off documented no PR.

### 2.3 Test matrix

Arqel suporta matrix de ambientes — cada PR roda tests em todas combinações.

**PHP matrix:**
- PHP: 8.3, 8.4
- Laravel: 12.x, 13.x
- DB: SQLite (dev), MySQL 8.0, MySQL 8.4, PostgreSQL 15, PostgreSQL 16
- OS: Ubuntu latest (Linux), macOS latest (check compat)

Total: 2 × 2 × 5 × 2 = 40 combinações no worst case. Otimização: smoke set (PHP 8.3 × Laravel 12 × SQLite × Ubuntu) roda em todo PR; full matrix roda em nightly + release candidates.

**JavaScript matrix:**
- Node: 20.9 (LTS), 22 (LTS current April 2026)
- Package manager: pnpm (primary), npm (secondary check)
- Browsers E2E: Chromium, Firefox, WebKit

### 2.4 Convenções de escrita de testes

**Pest (PHP):**

```php
// Boa: describe behavior, not implementation
it('allows users with admin role to create resources', function () {
    $admin = User::factory()->admin()->create();
    
    actingAs($admin)
        ->post('/admin/users', ['name' => 'Alice', 'email' => 'alice@test.com'])
        ->assertRedirect('/admin/users')
        ->assertSessionHas('success');
    
    expect(User::where('email', 'alice@test.com'))->toBeTrue();
});

// Má: implementation detail
it('calls UserCreationService::create once', function () {
    // Testa implementação, não behavior — frágil
});
```

**Vitest + Testing Library (JS):**

```typescript
// Boa: user-centric
test('user can toggle dark mode', async () => {
    render(<AppShell />)
    
    const toggle = screen.getByRole('button', { name: /toggle theme/i })
    await userEvent.click(toggle)
    
    expect(document.documentElement).toHaveClass('dark')
})

// Má: implementation detail
test('setState is called with dark', () => {
    // Testa interno, quebra em refactor
})
```

**Snapshot tests** são ok para serialization (shape de JSON props) mas **não** para UI — UI snapshots quebram em every CSS tweak.

### 2.5 Benchmark tests

Tests de performance em CI com thresholds:

```php
// tests/Performance/TableRenderBench.php
it('renders table with 100 rows in under 200ms', function () {
    $start = microtime(true);
    
    $response = get('/admin/users?per_page=100');
    
    $duration = (microtime(true) - $start) * 1000;
    expect($duration)->toBeLessThan(200);
});
```

Degradações >20% bloqueiam merge com warning.

## 3. CI/CD pipeline

### 3.1 Arquitetura do pipeline

Cobre INFRA-003 (Fase 1) e refinamentos em fases posteriores. Stack: GitHub Actions.

**Jobs em cada PR:**

```yaml
# .github/workflows/ci.yml
jobs:
  lint-php:        # PHP-CS-Fixer + PHPStan level max
  lint-js:         # ESLint + Prettier
  type-check-ts:   # tsc --noEmit em strict mode
  test-php:        # Matrix PHP × Laravel × DB
  test-js:         # Matrix Node × packages
  test-e2e:        # Playwright smoke set
  coverage-check:  # Compara coverage com base branch
  security-audit:  # composer audit + npm audit
  build-docs:      # Build docs site (preview deployment)
  build-js-packages: # Build todos npm packages
```

Total wall time target: <15 min no smoke path, <45 min full matrix.

### 3.2 Fail-fast vs fail-slow

- **Fail-fast**: lint, type-check, security audit — se falharem, aborta resto
- **Fail-slow**: testes — rodam tudo mesmo se um falhar (para ver full picture)

### 3.3 Caching aggressivo

CI performance depende de cache:

```yaml
- uses: actions/cache@v4
  with:
    path: |
      ~/.composer/cache
      vendor
      node_modules
      ~/.cache/ms-playwright
    key: ${{ runner.os }}-${{ hashFiles('composer.lock', 'pnpm-lock.yaml') }}
```

Cache TTL: 7 dias; invalidação por lockfile hash.

### 3.4 Preview deployments

Cada PR branch ganha preview:

- Docs site → Cloudflare Pages preview URL
- Playground app → Laravel Cloud preview environment
- Postado como comment no PR automaticamente

Permite review visual antes de merge.

### 3.5 Main branch protection

- Todos checks obrigatórios pass antes de merge
- Linear history (rebase-only, no merge commits)
- Signed commits recomendados (não obrigatório)
- DCO sign-off obrigatório em todos commits
- Aprovação de ≥1 maintainer

### 3.6 Nightly builds

Cron 3 AM UTC:

- Full test matrix (40 combinações)
- Performance benchmarks com histogram tracking
- Security audit profunda (snyk, trivy)
- Broken link check em docs site
- Dependency update check (Renovate auto-PRs)

Falhas notificam via Discord webhook #alerts.

## 4. Release management

### 4.1 Release cadence

**Minor releases (v1.x.0):** mensal, primeira terça do mês
**Patch releases (v1.x.y):** conforme necessário, <2 semanas após fix
**Major releases (vX.0.0):** anual ou bienal, com RCs em advance

RCs são publicadas 4-6 semanas antes de stable major. Feedback window extensiva.

### 4.2 Release pipeline

Cobre GOV-002 (Fase 1).

**Steps automatizados (scripts/release.mjs):**

1. Pre-flight checks:
   - Main branch clean
   - Todos checks green
   - CHANGELOG tem entries não-published
   - Security audit zero critical
2. Version bump:
   - Prompt ou `--version=X.Y.Z` flag
   - Atualiza todos `composer.json` + `package.json` (monorepo lockstep)
   - Atualiza version em `src/Arqel.php` (`const VERSION`)
3. CHANGELOG generation:
   - Parse Conventional Commits desde última tag
   - Categorize: Features, Fixes, Breaking, Chore
   - Prepend new section
4. Git operations:
   - Commit "chore: release vX.Y.Z"
   - Tag `vX.Y.Z` + sub-tags por package
   - Push
5. GitHub Actions release workflow triggered:
   - Build todos JS packages
   - Publish to npm com `NPM_TOKEN` secret
   - Split monorepo sub-repos via `splitsh/lite`
   - Trigger Packagist webhooks
   - Create GitHub Release com CHANGELOG notes
   - Deploy docs site com version selector
   - Post to Twitter + Discord via webhooks

Total time dry-run → live: <20 min.

### 4.3 Lockstep versioning

Todos pacotes Arqel compartilham version (Composer + npm). `arqel-dev/core 1.2.3` match `@arqel-dev/react 1.2.3`.

**Prós:**
- Reduz confusão (qual version é compat?)
- Simplifica release automation
- Users instalam com confidence

**Contras:**
- Patch pequeno em `arqel-dev/widgets` força bump em tudo
- Aceitável para projeto integrado como Arqel

**Exception:** plugins de terceiros no marketplace (Fase 4) versionam independently — convention opcional, não obrigada.

### 4.4 Release candidates (RCs)

Antes de major:

- `v2.0.0-rc.1` → 4 semanas
- `v2.0.0-rc.2` → 2 semanas (após feedback)
- `v2.0.0-rc.3` → 1 semana (se necessário)
- `v2.0.0` stable

RCs published em npm com tag `next`:

```bash
npm install @arqel-dev/react@next  # Gets RC
npm install @arqel-dev/react       # Gets stable
```

### 4.5 Hotfix process

Critical bug em production:

1. Identified + triaged
2. Branch `hotfix/X.Y.Z` from last stable tag
3. Fix + test
4. PR review (expedited, 1 maintainer sign-off)
5. Merge + cherry-pick to main
6. Release patch `X.Y.Z+1`
7. Post-mortem incident doc

Target: critical hotfix em <24h desde report.

### 4.6 Release channels

- **Stable** (npm `latest`, Packagist default): production-ready
- **Next** (npm `next`): RCs
- **Beta** (npm `beta`): early features, opt-in
- **Nightly** (npm `nightly`): daily builds, unstable

Users escolhem risk level. Defaults to stable.

## 5. Versionamento e deprecation

### 5.1 Semver estrito a partir de v1.0

**Breaking changes**: major only
**New features**: minor
**Bug fixes**: patch

Antes de v1.0 (fases 1-3 pre-release), breaking changes em minor são permitidas mas comunicadas. Após v1.0, strict semver.

### 5.2 Deprecation policy

Feature ou API antes de remoção:

1. **Announce**: minor release (`X.Y.0`) marca como `@deprecated` com mensagem
   - PHP: PHPDoc `@deprecated` + `trigger_error(..., E_USER_DEPRECATED)`
   - TypeScript: JSDoc `@deprecated` + console.warn em dev
2. **Grace period**: mínimo 1 minor (typically 2-3 minors, ~3-6 meses)
3. **Remove**: major release only
4. **Migration guide**: obrigatório em CHANGELOG e docs

**Exemplo:**

```php
/**
 * @deprecated since 1.3.0, use Field::richText() instead. Will be removed in 2.0.
 */
public static function wysiwyg(string $name): self
{
    trigger_error(
        'Field::wysiwyg() is deprecated since Arqel 1.3.0, use Field::richText() instead.',
        E_USER_DEPRECATED
    );
    return self::richText($name);
}
```

### 5.3 LTS (Long Term Support)

v1.0 tem LTS commitment desde release:

- **18 meses** de security patches (CVEs críticas/altas)
- **12 meses** de bug fixes

Após LTS window, users devem upgrade para próxima major. Security patches ainda disponíveis via sponsor tier (Fase 4, enterprise).

**Timeline exemplo:**

```
v1.0.0 release: Mês 0
v1.0 bug fixes: até mês 12
v1.0 security: até mês 18
v2.0.0 release: ~mês 18-24
```

### 5.4 Breaking changes criteria

Mudanças que contam como "breaking":

- Remoção de API pública (class, method, function)
- Mudança de signature (new required param, type change)
- Mudança de behavior observable em HTTP response shape
- Remoção de config key
- Mudança de default de config key
- Remoção de event ou listener hook
- Remoção de CSS variable exposta

Mudanças que **não** são breaking:

- Nova API adicionada
- Optional param adicionado
- Bug fix (mesmo que mude behavior — justificado)
- Internal refactors (non-public API)
- Upgrade de dependency minor
- Performance improvements

**Dúvida?** Default to "breaking" + major bump. Conservadorismo protects users.

## 6. Segurança

### 6.1 SECURITY.md

Cobre GOV-001. Arquivo raiz define:

- Versões suportadas (2 minors atuais + LTS)
- Canal de reporte: `security@arqel.dev` (preferred) ou GitHub Security Advisories
- PGP key (published em arqel.dev/security)
- Response SLA:
  - Acknowledgment: 48h
  - Initial triage: 72h
  - Fix + disclosure: 14 dias para critical, 30 dias para high, 90 dias para medium

### 6.2 Disclosure process

1. Reporter contacta security@arqel.dev com detalhes + reproducer
2. Acknowledge em 48h
3. Triagem: severity classification (CVSS)
4. Fix em private branch
5. Coordinated disclosure window (negotiated com reporter):
   - Critical: 14 dias max
   - High: 30 dias
   - Medium: 90 dias
6. Release security patch across supported versions
7. GitHub Security Advisory published
8. Blog post explicando issue + fix
9. Hall of Fame credit (opt-in reporter)
10. CVE assigned se applicable (via GitHub ou MITRE)

### 6.3 Dependency auditing

**Automated:**
- `composer audit` em cada CI run
- `pnpm audit` em cada CI run
- Renovate bot abre PRs semanalmente para updates
- Dependabot alerts em GitHub

**Manual:**
- Quarterly review de todas dependencies
- Justify por que cada dep existe
- Remove deps não usadas

### 6.4 Supply chain security

- npm: publish com `--provenance` flag (npm 9.5+)
- Composer: Packagist com 2FA obrigatório em account
- GitHub: branch protection + signed commits em releases
- Secrets em CI: minimum scopes, rotated quarterly
- Build reproducibility: lockfiles commited, deterministic builds

### 6.5 Secure coding guidelines

PHP:

- Nunca raw queries com user input (use Eloquent/Query Builder)
- Escape output em Blade (`{{ $var }}` auto-escapes; `{!! !!}` com cuidado)
- Validate ALL input via FormRequest
- Authorize ALL actions via Policy
- CSRF tokens em todas state-changing requests
- File uploads: validate type + size + scan com ClamAV (opt-in)
- Encryption: use `Crypt` facade, never home-grown crypto

JavaScript:

- Nunca innerHTML com user content (use textContent ou sanitize com DOMPurify)
- Escape em templates (React auto-escapes; dangerouslySetInnerHTML rare + sanitized)
- CSP headers restritivos em production
- Subresource Integrity (SRI) em CDN assets
- Environment vars com prefix `VITE_` para client (resto é server-only)

### 6.6 Security testing

- Static analysis: PHPStan max level, Psalm, Snyk Code
- Dependency scanning: snyk, Dependabot, Renovate
- Secret scanning: GitGuardian, GitHub secret scanning
- DAST: OWASP ZAP contra playground em nightly
- Penetration test: annual, external vendor, findings → public summary

## 7. Governança e contribuição

### 7.1 Estrutura de governança

**Fase 1-3** (bootstrap): BDFL/creator-led
- Decisions by creator + 1-2 early maintainers
- Fast iteration, baixa ceremony

**Fase 3+** (pós v1.0): Steering Committee
- 3-5 maintainers votados pela community
- RFC process para breaking changes
- Documented decision-making

Cobre GOV-V3-003.

### 7.2 Maintainer criteria

Para tornar-se maintainer:

- Contribuições significativas por ≥6 meses
- Aprovado pelos atuais maintainers (simple majority)
- Technical depth + domain knowledge demonstrado
- Communication skills (respectful, clear)
- Available >4h/semana para triagem de issues + reviews

Responsabilidades:

- Triage issues em <72h
- Review PRs em <7 dias
- Attend monthly maintainer sync (virtual)
- Mentor contributors
- Security response rotation (primary/backup)

### 7.3 CONTRIBUTING.md

Cobre GOV-003. Inclui:

- Welcome + code of conduct link
- Setup dev environment (clone, install, test)
- Project structure overview
- Workflow: fork → branch → PR
- Convenção de commits: [Conventional Commits](https://www.conventionalcommits.org/)
- DCO sign-off obrigatório
- Como escrever tests (com exemplos)
- Como documentar
- Troubleshooting comum

### 7.4 Conventional Commits

**Formato:**

```
<type>(<scope>): <description>

<optional body>

<optional footer>
```

**Types:**
- `feat`: nova feature
- `fix`: bug fix
- `docs`: só docs
- `style`: formatting, no code change
- `refactor`: refactor, no behavior change
- `perf`: performance improvement
- `test`: adding tests
- `build`: build system
- `ci`: CI config
- `chore`: misc

**Scopes (optional):** nome do pacote (e.g., `feat(table): add virtual scrolling`)

**Breaking changes:** `!` após type ou `BREAKING CHANGE:` no footer

Exemplos:

```
feat(fields): add AiTranslate field for multi-language content

Implements RF-F-13 from spec. Supports Claude, OpenAI, and Ollama
providers via @arqel-dev/ai package.

Closes #142
```

```
fix(auth)!: policy.canSee now correctly handles null user

BREAKING CHANGE: Previously returned false for null users; now
returns false only if policy defines explicit guest deny. Update
your policies if you relied on the old behavior.
```

### 7.5 DCO (Developer Certificate of Origin)

Todos commits signed-off:

```bash
git commit --signoff
```

Adds footer:

```
Signed-off-by: Your Name <you@example.com>
```

Certifica que contributor tem rights para submit code. DCO bot enforça em PRs.

### 7.6 Code of Conduct

Arqel adopta [Contributor Covenant](https://www.contributor-covenant.org/) v2.1.

Violations reportadas a `conduct@arqel.dev`. Handled privately e rapidamente. Possible outcomes: warning, temporary ban, permanent ban.

### 7.7 Decision-making process

**Small changes** (bug fixes, minor features): 1 maintainer approval + merge.

**Medium changes** (new feature, API addition): 2 maintainer approval.

**Large changes** (breaking change, new package, architectural shift): RFC process:

1. Author opens RFC issue (template: problem, proposal, alternatives, drawbacks)
2. Community discussion ≥2 semanas
3. Steering committee vote
4. Simple majority wins
5. Implementation PR references RFC

RFC template published em `.github/ISSUE_TEMPLATE/rfc.yml`.

## 8. Code review e PR process

### 8.1 PR checklist

Template `.github/PULL_REQUEST_TEMPLATE.md`:

```markdown
## Descrição

<!-- O que mudou e por quê? Link issue se aplicável -->

## Tipo

- [ ] Bug fix (non-breaking)
- [ ] New feature (non-breaking)
- [ ] Breaking change
- [ ] Docs only

## Checklist

- [ ] Testes passam localmente
- [ ] Novos testes para novo código
- [ ] Coverage mantido ou melhorado
- [ ] Lint passa
- [ ] Type check passa
- [ ] Docs atualizadas se applicable
- [ ] CHANGELOG entry não necessário (auto-generated)
- [ ] DCO signed em todos commits
- [ ] Screenshots/GIFs para mudanças visuais
```

### 8.2 Review criteria

Reviewers check:

**Correctness:**
- Código resolve o problema declarado
- Edge cases considerados
- No regressions

**Quality:**
- Follows project conventions
- Readable, maintainable
- No duplication
- Appropriate abstractions

**Tests:**
- Tests cobrem new code
- Tests cobrem bug fix (regression test)
- Tests são descriptive

**Performance:**
- No N+1 queries introduced
- No memory leaks
- Bundle size impact acceptable

**Security:**
- Input validation
- Authorization checks
- No secrets hardcoded

**Docs:**
- Public API documented
- SKILL.md updated se conventions mudaram
- Comments onde non-obvious

### 8.3 Review tone

Reviews são conversational, não combative:

✅ "What do you think about extracting this to a helper? It's used in 3 places."
❌ "Extract this to a helper."

✅ "I'm not sure I understand the edge case here — can you walk me through it?"
❌ "This doesn't handle the edge case."

✅ "Nit: missing space after comma (optional)."
❌ "Formatting is wrong."

Prefixes úteis:
- `nit:` — trivial, OK to merge sem fix
- `suggestion:` — consider, but PR author decide
- `blocker:` — precisa fix antes de merge
- `praise:` — celebrar bom código

### 8.4 Merge strategies

- **Squash merge** (default): 1 commit final com PR title + body
- **Rebase merge**: quando PR tem commits bem-curados (linear history)
- **Merge commit**: nunca (preserva noise)

### 8.5 Stale PRs

PRs sem atividade >30 dias:

1. Maintainer pings author
2. Se no response em 14 dias, close com comment ("feel free to reopen")
3. Exceptions: WIP PRs mark como draft

## 9. Qualidade de código

### 9.1 PHP standards

- **Version:** PHP 8.3+
- **Style:** PSR-12 + Laravel conventions
- **Tool:** PHP-CS-Fixer com `.php-cs-fixer.dist.php` custom config
- **Static analysis:** PHPStan level max
- **Types:** `declare(strict_types=1);` em todos files
- **Namespacing:** `Arqel\*` para core, `Arqel\{Package}\*` para packages
- **Final by default:** classes final unless intended for extension

Exemplo:

```php
<?php

declare(strict_types=1);

namespace Arqel\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;

final class Field
{
    public function __construct(
        protected readonly string $name,
    ) {}
    
    // ...
}
```

### 9.2 TypeScript standards

- **Version:** TypeScript 5.6+
- **Config:** `strict: true`, `noUncheckedIndexedAccess: true`, `exactOptionalPropertyTypes: true`
- **Style:** ESLint + Prettier
- **React:** functional components only, hooks over HOCs
- **Exports:** named exports preferred; default export ok for pages/components

Exemplo:

```typescript
import { useState } from 'react'
import type { FieldComponentProps } from '@arqel-dev/types'

export function TextInput({ field, value, onChange, error }: FieldComponentProps<string>) {
    const [focused, setFocused] = useState(false)
    
    return (
        <input
            type="text"
            value={value ?? ''}
            onChange={(e) => onChange(e.target.value)}
            onFocus={() => setFocused(true)}
            onBlur={() => setFocused(false)}
            aria-invalid={!!error}
            aria-describedby={error ? `${field.name}-error` : undefined}
        />
    )
}
```

### 9.3 Lint rules customizadas

Regras específicas Arqel enforced via ESLint plugins + PHPStan custom rules:

- **No direct DB queries em controllers** (PHP): use Resource + Eloquent
- **No Policies bypass** (PHP): proibe `@can` com `:without-auth`
- **No inline styles** (React): use Tailwind classes or CSS vars
- **No `any` type** (TS): explicit types obrigatórios
- **No `console.log` em produção** (TS): ESLint plugin remove em build

### 9.4 Complexity limits

- **PHP methods:** <50 linhas idealmente, <100 max
- **React components:** <200 linhas; extract se maior
- **Cyclomatic complexity:** <10 per function (PHPStan/ESLint rules)

Violations raise warnings, não blockers. Maintainer review decide.

### 9.5 File size limits

- **Source files:** <500 linhas warning, <1000 hard limit
- **Total PR size:** >500 linhas changed → request break-up

## 10. Documentação e SKILL.md

### 10.1 Docs hierarchy

```
/docs (VitePress site)
├── guide/           # Narrative guides
├── reference/       # API reference (auto-gen)
├── advanced/        # Advanced topics
├── recipes/         # Cookbook patterns
└── changelog/       # Release notes

/packages/{pkg}
├── SKILL.md         # AI agent context
└── README.md        # Package overview
```

### 10.2 SKILL.md convention

Cada pacote tem SKILL.md com estrutura canônica (ver `00-index.md` §5):

```markdown
# SKILL.md — arqel/{package}

## Purpose
<1-paragraph description>

## Key Contracts
<Public APIs, interfaces, traits>

## Conventions
<Naming, patterns, etc.>

## Examples
<3-5 working examples>

## Anti-patterns
<Common mistakes>

## Related
<Links to related packages, docs>
```

SKILL.md é **leitura obrigatória** pelos agents antes de usar o pacote. Review rigoroso em PRs.

### 10.3 Docblocks

Público API sempre documentado:

```php
/**
 * Adds a text field to the form.
 *
 * @param string $name The field name (database column)
 * @return self For fluent chaining
 *
 * @example
 *   Field::text('title')->required()->maxLength(255)
 */
public static function text(string $name): self
```

Internal methods: docblocks optional, mas code deve ser self-documenting.

### 10.4 README.md per package

Cada pacote tem README.md no raiz:

- Badges (version, license, tests)
- 1-paragraph description
- Installation
- Quick start (5 lines code)
- Link para docs site
- License + credits

### 10.5 CHANGELOG

Auto-gerado de Conventional Commits. Format [Keep a Changelog](https://keepachangelog.com/):

```markdown
## [1.3.0] - 2026-04-01

### Added
- AI fields (AiText, AiTranslate, AiSelect, AiExtract) (#142)
- Multi-step Wizard form component (#148)

### Changed
- TableFilters now support async options (#150)

### Fixed
- Fixed N+1 query in belongsTo field search (#156)

### Deprecated
- `Field::wysiwyg()` deprecated, use `Field::richText()` (#145)

### Removed
- (none)

### Security
- (none)
```

## 11. Monitoring e observabilidade

### 11.1 Projeto internal (Arqel's own infrastructure)

Arqel opera vários assets:

- Docs site (arqel.dev)
- Marketplace (arqel.dev/marketplace) — Fase 4
- Playground (arqel.dev/playground) — Fase 4
- Demo apps
- Blog
- Status page

**Monitoring:**
- Uptime: UptimeRobot checks every 5 min
- Logs: centralized via Laravel Pulse + Sentry
- Errors: Sentry for frontend + backend
- Metrics: Grafana Cloud (free tier)
- Status page: status.arqel.dev (Statuspage ou open-source equivalent)

### 11.2 User-facing observability

Features Arqel expose to users:

- Laravel Pulse cards (Fase 4 LCLOUD-003)
- Query log em devtools (Fase 4 DEVTOOLS-007)
- Audit log (Fase 2 AUDIT)

### 11.3 Metrics tracked

**Package health:**
- Downloads (Packagist + npm)
- Star count (GitHub)
- Issue count (open/closed)
- PR count (open/merged)
- Time to first response
- Time to resolution
- Test flakiness rate

**Community health:**
- Discord active members
- GitHub Discussions activity
- Blog post engagement
- Social media reach

**Product usage (opt-in telemetry, Fase 4):**
- Anonymized feature usage
- Error rates
- Performance percentiles

Telemetry opt-in always. Never personal data.

## 12. Incident response

### 12.1 Classification

**Severity levels:**

- **SEV-1** (Critical): security breach, data loss, service completamente down
- **SEV-2** (High): major feature broken affecting many users
- **SEV-3** (Medium): minor feature broken, workaround exists
- **SEV-4** (Low): cosmetic, edge case

### 12.2 Response times

| Severity | Acknowledge | Initial response | Resolution target |
|---|---|---|---|
| SEV-1 | 1h | 4h | 24h |
| SEV-2 | 4h | 24h | 7 dias |
| SEV-3 | 24h | 3 dias | 30 dias |
| SEV-4 | 7 dias | 14 dias | Next release |

### 12.3 On-call rotation (post-Fase 3)

Primary + backup maintainer rotate weekly:

- Monitor alerts (Discord #alerts, PagerDuty-like service)
- Initial triage
- Escalate se needed

Compensation: enterprise support revenue funds on-call (Fase 4 onwards).

### 12.4 Post-mortems

Para cada SEV-1 e SEV-2:

1. Timeline documentado (what happened, when)
2. Root cause analysis
3. Impact assessment (users affected, duration)
4. Action items (prevent recurrence)
5. Public post em blog se customer-facing

Blameless culture — foco em processes, não people.

### 12.5 Communication durante incident

- Status page updated em tempo real
- Discord #announcements
- Twitter critical updates
- Email notifications to enterprise customers

Transparency builds trust. Over-communicate durante incidents.

---

## Conclusão

Este documento completa o planejamento fundacional do Arqel. Junto com os 11 documentos anteriores, fornece base completa para:

- **Arquitetura** (docs 01-02): o que Arqel faz e como
- **Decisões** (doc 03): por que escolhas foram feitas
- **Estrutura** (docs 04-06): como código é organizado e como APIs se vêem
- **Roadmap** (doc 07): sequência de entrega
- **Execução** (docs 08-11): tickets detalhados por fase (321 tickets totais)
- **Operação** (doc 12): como trabalhamos ao longo do tempo

**Próximos passos:**

1. Validar nomes e domínios arqel.dev/.com/.io antes de qualquer publicação
2. Setup GitHub org (`arqel`), Packagist, npm scope
3. Executar INFRA-001 a INFRA-005 (Sprint 0)
4. Começar CORE-001

O planejamento é um ponto de partida, não um contrato imutável. Esperamos ajustar conforme realidade bate na implementação — essa é a natureza de construir software. Mas ter esse mapa reduz ambiguidade e permite maintainers + contributors mergulharem com contexto.

**Bem-vindo ao Arqel. Vamos construir.**
