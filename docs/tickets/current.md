# Ticket ativo

> Este arquivo é atualizado automaticamente após cada ticket completado.
> Serve como ponteiro para o Claude Code saber onde continuar.

## 🎯 Ticket corrente

**[CORE-003] Comando Artisan `arqel:install`**

**Fase:** 1 (MVP) • **Sprint:** 1 (CORE foundational)
**Prioridade:** P0 • **Estimativa:** M
**Depende de:** CORE-002 ✅

**Localização no planejamento:** `PLANNING/08-fase-1-mvp.md` §3 (CORE-003, linha 485).

## 📋 Sprint 0 — Backlog sequencial

Ordem canónica (fonte: `PLANNING/08-fase-1-mvp.md` §2):

- [x] **INFRA-001** — Inicialização do monorepo Git ✅ 2026-04-17
- [x] **INFRA-002** — Configuração pnpm workspace + Composer path repositories ✅ 2026-04-17
- [x] **INFRA-003** — Configuração de ferramentas de formatação e lint (PHP e JS) ✅ 2026-04-17
- [x] **INFRA-004** — Configuração do pipeline de CI no GitHub Actions ✅ 2026-04-17
- [x] **INFRA-005** — Configuração de Renovate Bot + dependency grouping ✅ 2026-04-17

> **Nota:** a ordem em `CLAUDE.md` e `KICKOFF.md` divergia da canónica; a fonte é `PLANNING/08-fase-1-mvp.md` (ver regra de ouro #1 em `CLAUDE.md`).

## 📋 Paralelo ao Sprint 0

- [x] **GOV-001** — SECURITY.md e processo de disclosure ✅ 2026-04-17
- [x] **GOV-003** — CONTRIBUTING.md + PR templates + DCO bot ✅ 2026-04-17 (App instalação pendente)

## ✅ Completados

### CORE-002 — `ArqelServiceProvider` com auto-discovery (2026-04-27)

**Entregue:**

- `packages/core/src/ArqelServiceProvider.php` — `final` class estende `Spatie\LaravelPackageTools\PackageServiceProvider`. Configura `name('arqel')`, `hasConfigFile('arqel')`, `hasInstallCommand` com `publishConfigFile()` + `askToStarRepoOnGitHub('arqel/arqel')`. Em `packageBooted()` regista singletons (`ResourceRegistry`, `PanelRegistry`) e alias `arqel` → `PanelRegistry`. Constante tipada `public const string FACADE_ACCESSOR = 'arqel'`
- `packages/core/src/Registries/ResourceRegistry.php` — stub `final class` (preenchido em CORE-004)
- `packages/core/src/Registries/PanelRegistry.php` — stub `final class` (preenchido em CORE-005)
- `packages/core/src/Facades/Arqel.php` — `final` Facade que aponta para o accessor `arqel`
- `packages/core/config/arqel.php` — config inicial (`path`, `resources.path`, `resources.namespace`, `auth.guard`)
- `packages/core/composer.json` — adicionado `extra.laravel.providers: ["Arqel\\Core\\ArqelServiceProvider"]` (auto-discovery ADR-018)
- `packages/core/tests/TestCase.php` — base abstract estende Orchestra Testbench, regista `ArqelServiceProvider`
- `packages/core/tests/Pest.php` — `uses(TestCase::class)->in('Feature', 'Unit')`
- `packages/core/tests/Feature/ArqelServiceProviderTest.php` — 6 testes: singletons, alias `arqel`, facade root, config merge, comando `arqel:install` registado
- `packages/core/tests/Unit/FacadeTest.php` — 2 testes: facade root + constante `FACADE_ACCESSOR`

**Validações:**

- `vendor/bin/pest` → 8/8 passed (14 assertions, 0.12s)
- `vendor/bin/pint --test` (root) → pass (após auto-fix `single_line_empty_body` nos stubs)
- `bash scripts/phpstan.sh` (root, level max) → No errors em 4 ficheiros analisados
- Auto-discovery confirmado: app de teste boota o ServiceProvider sem registo manual

**Decisões autónomas:**

- Não adicionei `hasViews('arqel')` nem `hasTranslations()` (estavam no exemplo do ticket): nenhum dos dois directórios existe ainda no package, e Spatie levanta erro se referir directórios inexistentes. Serão adicionados quando os primeiros views/translations chegarem (provavelmente CORE-005 + UI tickets)
- Constante `FACADE_ACCESSOR` adicionada na classe (PHP 8.3 typed constant) para evitar string mágica duplicada no Facade e nos testes
- Coverage driver (Xdebug/PCOV) não está instalado no ambiente — `pest --coverage --min=90` falha com "No code coverage driver". O critério de coverage do ticket fica adiado para o pipeline CI (que instala PCOV). Localmente os 8 testes passam todos

**Pendente humano:**

- Instalar PCOV ou Xdebug localmente para validar coverage ≥90% em desenvolvimento (workflow `test-matrix.yml` já o faz no CI)

### CORE-001 — Esqueleto do pacote `arqel/core` com composer.json e PSR-4 (2026-04-17)

**Entregue:**

- `packages/core/composer.json` — name `arqel/core`, PHP `^8.3`, Laravel `^12.0|^13.0`, Inertia 3, spatie/laravel-package-tools 1.16+. Dev deps: Orchestra Testbench 10, Pest 3, pest-plugin-laravel 3, Larastan 3. PSR-4 `Arqel\Core\` → `src/`; PSR-4 dev `Arqel\Core\Tests\` → `tests/`
- Estrutura: `src/`, `tests/Feature/`, `tests/Unit/`, `config/` (todos com `.gitkeep` por agora)
- `README.md` com badges (License/PHP/Laravel/Status), visão do pacote, convenções e links
- `SKILL.md` canónico — Purpose, Key Contracts, Conventions, Common tasks, Anti-patterns, Related
- `phpunit.xml` para Pest — SQLite in-memory, APP_ENV=testing, strict output
- `pest.xml` stub (a config real vive em phpunit.xml)
- `.gitattributes` local — `export-ignore` para `tests/`, `phpunit.xml`, `pest.xml`, `SKILL.md` (não vão no Packagist tarball)

**Root monorepo alterado:**

- `composer.json` root: `require-dev` agora tem `"arqel/core": "@dev"` (constraint obrigatória para path repos em root `minimum-stability: stable`)
- `composer.lock` regenerado — `arqel/core (dev-main): Symlinking from packages/core` confirma path repository activo
- `.gitignore` — adicionado `packages/*/composer.lock` e `packages-js/*/pnpm-lock.yaml` (lockfiles canónicos vivem só na raiz)
- `phpstan.neon` — exclude patterns corrigidas para `packages/*/vendor/*` (sufixo `/*` obrigatório) e `reportUnmatchedIgnoredErrors: false`
- `scripts/phpstan.sh` — detecção refinada para usar `find -name '*.php'` em vez de `ls dir/`, para saltar graciosamente quando só há `.gitkeep`

**Validações:**

- `composer validate` em `packages/core/` → OK
- `composer install` root → instala arqel/core via path repo (symlink em `vendor/arqel/core`)
- `composer dump-autoload` gera classmap sem erro
- `composer run analyse` → skip gracioso (ainda não há `.php` em src)
- Autoload: `require 'vendor/autoload.php'` no root carrega o namespace `Arqel\Core\`

**Desvios:**

- Ticket pedia `"arqel/core": "*"` no root — composer rejeita porque path repo resolve em `dev-main`. Usei `"@dev"` (standard Composer para path repos em monorepos). Solução aplicável ao padrão para todos os packages subsequentes

### GOV-001 — SECURITY.md e processo de disclosure (2026-04-17)

**Entregue:**

- `SECURITY.md` expandido: SLA explícito (acknowledgement 48h, triage 5d úteis, patch críticas 14d, altas 30d, médias/baixas 90d), processo passo-a-passo com ID interno `ARQEL-SEC-YYYY-NNN`, CVSS 3.1 para severidade, âmbito dentro/fora detalhado, boas práticas para integradores, Hall of Fame mencionado, PGP adiado para ticket futuro (justificado)
- Canal de contacto: GitHub Security Advisories (preferido) + `security@arqel.dev`
- Referências cruzadas a `CODE_OF_CONDUCT.md`, `CONTRIBUTING.md`, e `CHANGELOG.md`

**Pendente humano:**

- Registar `security@arqel.dev` como endereço real (alias para equipa-core)
- Configurar GitHub Security Advisories no repo (Settings → Security)
- Testar dry-run do processo (report simulado)

### GOV-003 — CONTRIBUTING.md + PR/Issue templates + DCO (2026-04-17)

**Entregue:**

- `CONTRIBUTING.md` completo: tipos de contribuição, setup dev, fluxo de trabalho (branch → PR → review), Conventional Commits com scopes canónicos, DCO detalhado, instruções de testes (Pest, Vitest, Playwright), docs em PT-BR, secção "O que NÃO fazer", reconhecimento
- `.github/PULL_REQUEST_TEMPLATE.md` com checklist: tipo de mudança, como foi testado, DCO, ADRs, coverage, breaking changes, screenshots, notas para reviewers
- `.github/ISSUE_TEMPLATE/config.yml` — blank issues desabilitadas, contact links para Discussions + Security + Docs
- `.github/ISSUE_TEMPLATE/bug_report.yml` — form estruturado com pré-verificações, package afectado, versão, ambiente, repro steps, logs
- `.github/ISSUE_TEMPLATE/feature_request.yml` — dropdown de package, problem + proposta + alternativas, estimativa de impacto, disponibilidade para implementar
- `.github/ISSUE_TEMPLATE/question.yml` — leve, redireciona maioria para Discussions; só para gaps de docs

**Pendente humano:**

- Instalar [DCO GitHub App](https://github.com/apps/dco) no repo (o hook local já enforça; o App enforça em PRs de forks)
- Criar labels: `bug`, `enhancement`, `question`, `triage`, `major-update`, `dependencies`, `security`
- Verificar rendering dos templates em GitHub UI (requer push)

### INFRA-005 — Configuração de Renovate Bot + dependency grouping (2026-04-17)

**Entregue:**

- `renovate.json` com presets `config:recommended`, `group:monorepos`, `group:recommended`, `helpers:pinGitHubActionDigests`
- Schedule semanal "before 5am every monday" (timezone Europe/Lisbon)
- Groups: `react-monorepo`, `inertia-stack`, `laravel-stack`, `testing`, `lint-format`, `hooks`, `github-actions`
- Auto-merge patch updates em dev deps
- Major updates abertos como drafts
- Vulnerability alerts habilitados
- Lockfile maintenance mensal
- Sign-off automático nos commits do bot (respeita DCO)
- Internal workspace packages (`@arqel/*`, `arqel/*` excluindo registry) ignorados
- `.github/dependabot.yml` reduzido a `github-actions` apenas (Renovate gere composer e npm; Dependabot Security Updates continuam activos automaticamente no repo)

**Pendente humano:**

- Instalar Renovate GitHub App no repo (https://github.com/apps/renovate)
- Validar em `https://config-validator.renovatebot.com/`
- Confirmar primeiro dashboard issue após primeira run

### INFRA-004 — Configuração do pipeline de CI no GitHub Actions (2026-04-17)

**Entregue:**

- `.github/workflows/ci.yml` — jobs `lint-php` (Pint + PHPStan via `composer run analyse` wrapper), `lint-js` (Biome), `typecheck` (workspace `tsc --noEmit`), `test-js` (Vitest workspace), `commitlint` (valida commits do PR). Concurrency com `cancel-in-progress`. Caches Composer e pnpm
- `.github/workflows/test-matrix.yml` — matrix PHP `[8.3, 8.4]` × Laravel `[12.*, 13.*]` × DB `[mysql, postgres]`. Services MySQL 8.4 e Postgres 17. Preflight skip se ainda não há `packages/*/src`. Pin de Laravel version por matrix slot. Job sentinela `matrix-ok` para branch protection
- `.github/workflows/security.yml` — CodeQL JS/TS + PHP (best-effort `continue-on-error`), `composer audit`, `pnpm audit`. Schedule diário 06:00 UTC
- `.github/workflows/docs-deploy.yml` — placeholder (completado em ticket DOCS posterior)
- `.github/workflows/release.yml` — placeholder (completado em GOV-002)
- `.github/dependabot.yml` — groups `laravel-stack`, `inertia-stack`, `testing`, `lint-analyse` (composer); `react-monorepo`, `inertia-stack`, `testing`, `lint-format`, `hooks` (npm); github-actions mensais

**Decisões autónomas:**

- Todos os usos de variáveis derivadas de `github.event.*` passaram por `env:` antes de `run:` (mitigação de injection conforme hook de segurança alerta)
- `lint-php` usa `composer run analyse` (que passa pelo wrapper `scripts/phpstan.sh`) — tolera estado sem packages
- Coverage gate `85%` referido no ticket fica nos próprios Pest runs por package (matrix invoca `vendor/bin/pest --coverage --min=85` quando houver packages). Codecov upload só no slot canónico (PHP 8.4, Laravel 13, mysql)
- CodeQL PHP: marcado `continue-on-error: true` porque em 2026-04 PHP está em beta. Removível quando estabilizar

**Validações:**

- `python3 yaml.safe_load` valida sintaxe de todos os 5 workflows e do dependabot.yml
- Jobs de lint/typecheck/test-js tolerantes a estado vazio (já testado localmente via scripts `pnpm run lint|typecheck|test`)

**Pendente humano:**

- Push para remoto e habilitação real do dependabot e CodeQL no repo (requer admin)
- Branch protection em `main` — exige CI verde + 1 review (critério de aceite do ticket; depende do push)

### INFRA-003 — Configuração de ferramentas de formatação e lint (PHP e JS) (2026-04-17)

**Entregue:**

- `pint.json` — preset Laravel + `declare_strict_types`, `final_class`, `ordered_imports` alfabético, `single_quote`, `trailing_comma_in_multiline`
- `phpstan.neon` — level `max`, paths `packages/`, exclusões para tests/vendor/database/config; tmpDir `.phpstan.cache`; parallel 4. **Nota:** Larastan NÃO carregado no root (porque o root não depende de laravel/framework); cada package Laravel-dependente estenderá este ficheiro e incluirá a extensão Larastan localmente
- `biome.json` — Biome 2.4.12, formatter 2-space LF, JS single quotes + JSX double + trailing commas + sempre-semi, linter recommended + `noExplicitAny=error`, `noConsole=warn`, `organizeImports` on save. Exclui `pint.json`/`composer.json` (seguem convenção PHP 4-space)
- `tsconfig.base.json` — `strict: true`, `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes`, `verbatimModuleSyntax`, `moduleResolution: bundler`, target ES2022
- `commitlint.config.mjs` — tipos e scopes canónicos (ver PLANNING/00-index.md)
- `.husky/pre-commit` → `pnpm exec lint-staged`
- `.husky/commit-msg` → commitlint + validação DCO sign-off
- `lint-staged` config no `package.json`: `.{ts,tsx,js,…}` → biome; `.php` → pint --dirty
- Scripts raiz: `pnpm lint`, `pnpm lint:fix`, `pnpm format`, `pnpm typecheck`, `pnpm lint:php`, `pnpm format:php`, `pnpm analyse:php`; `composer run lint|format|analyse`
- `scripts/phpstan.sh` — wrapper que saía com exit 0 quando não há `packages/*/src` (toolerância ao estado inicial)
- `scripts/init.sh` — removida criação manual de `.git/hooks/commit-msg` (agora gerido por husky via `pnpm install → prepare`)

**Dependências adicionadas:**

- Composer (require-dev): `laravel/pint ^1.29`, `larastan/larastan ^3.9`
- npm (devDependencies root): `@biomejs/biome ^2.4.12`, `typescript ^6.0.3`, `husky ^9.1.7`, `lint-staged ^16.4.0`, `@commitlint/cli ^20.5.0`, `@commitlint/config-conventional ^20.5.0`

**Validações:**

- `pnpm run lint` → biome check OK em 4 ficheiros
- `pnpm run typecheck` → workspace no-op (sem packages)
- `vendor/bin/pint --test` → `{"result":"pass"}`
- `composer run analyse` → skip gracioso (sem packages/*/src)
- `pnpm exec commitlint` bloqueia mensagens inválidas (testado com mensagem sem type) e aceita mensagens Conventional + DCO

**Desvios e decisões autónomas:**

- Larastan aplicado por-package (não no root) — single-source phpstan config no root não funciona sem laravel/framework, e instalar Laravel na raiz do monorepo é desnecessário. Cada package PHP que depender de Laravel vai incluir `extension.neon` no seu phpstan.neon local
- TypeScript `^6.0.3` em vez de `5.5+` — 6.x é o actual estável em 2026-04; satisfaz requisito mínimo
- Biome 2.4.12 em vez de versão específica do ticket (não fixada) — usa última disponível
- Husky substitui o hook manual `.git/hooks/commit-msg` que o `init.sh` antigo criava (evitava conflito)

### INFRA-002 — Configuração pnpm workspace + Composer path repositories (2026-04-17)

**Entregue:**

- `pnpm-workspace.yaml` com globs `packages-js/*` e `apps/*`
- `package.json` raiz: `private: true`, `packageManager: pnpm@10.33.0`, `engines.node: >=20.9.0`, scripts recursivos (`build`, `dev`, `test`, `lint`, `typecheck`, `test:all`, `clean`)
- `composer.json` raiz com `type: project`, path repository apontando para `packages/*` (symlink), scripts placeholder que serão preenchidos em INFRA-003
- `.npmrc` com `auto-install-peers=true`, `strict-peer-dependencies=false`, `link-workspace-packages=true`
- `README.md` com bloco de pré-requisitos e comandos principais

**Validações:**

- `pnpm install` ok (workspace resolve, sem packages ainda)
- `composer install` ok (path repositories activos, lockfile gerado)
- `pnpm run build|lint|test` retornam "No projects matched" (esperado — ainda sem packages)

**Desvios do ticket canónico:**

- Ticket pedia `pnpm@9.x`; uso `pnpm@10.33.0` (versão estável actual via corepack). Não há ADR sobre versão de pnpm; decisão autónoma registada aqui
- Ainda não existem packages para preencher `require-dev`; deixei vazio — será populado à medida que os packages CORE|FIELDS|etc. forem criados

### INFRA-001 — Inicialização do monorepo Git (2026-04-17)

**Entregue:**

- Estrutura top-level criada: `apps/`, `packages/`, `packages-js/`, `registry/`, `docs-content/`, `examples/`, `scripts/`, `.github/workflows/` (todos com `.gitkeep`)
- `.gitattributes` com LF line endings, binary detection e export-ignore
- `.editorconfig` com 4 espaços PHP / 2 espaços TS-JS-YAML-JSON / UTF-8 / LF
- `LICENSE` MIT com copyright "Arqel Contributors" (2026)
- `CHANGELOG.md` com cabeçalho "Unreleased"
- `CODE_OF_CONDUCT.md` Contributor Covenant 2.1 (PT-BR)
- `SECURITY.md` com política de divulgação (placeholder — GOV-001 expande)
- Branch local renomeada para `main`
- `README.md` com links corrigidos para ficheiros existentes
- `.nvmrc` fixado em `22.22.0` + `scripts/init.sh` corrigido para usar corepack

**Notas:**

- O repositório remoto está em `diogocoutinho/arqel` (acordado com o utilizador), não `arqel/arqel` — push à org oficial fica para quando a org for criada
- Commit `637f870` (o inicial) antecede DCO hooks e não tem sign-off; é aceitável conforme nota do `KICKOFF.md` §Passo 3
- Branch protection fica para após INFRA-004 (CI verde como pré-requisito)
- Push do `main` e eliminação do `origin/master` remoto ficam para o utilizador executar manualmente

## 📊 Progresso geral

**Fase 1 MVP:** 8/123 tickets (6.5%)
**Sprint 0 (Setup):** 7/7 ✅ 🎉
**Sprint 1 (CORE):** 2/15 tickets (CORE-001 ✅, CORE-002 ✅)

## 🔄 Ao completar o ticket ativo

O Claude Code deve:

1. Marcar checkbox [x] acima
2. Mover entry para seção "✅ Completados" com data
3. Atualizar "Ticket corrente" para próximo na sequência
4. Incrementar contadores de progresso
5. Commit este arquivo junto com o código: `chore(tickets): complete INFRA-00X, start INFRA-00Y`

## 🚦 Critérios de saída Sprint 0

Todos os 5 tickets INFRA completos + verificação:

- [ ] `git clone` + `./scripts/init.sh` resulta em repo funcional
- [ ] `pnpm test:all` passa (mesmo com poucos testes reais ainda)
- [ ] CI roda e passa em PR mock
- [ ] `./scripts/release.mjs --dry-run` executa sem erro
- [ ] Pre-commit hook bloqueia commit com lint errors

**Ao cumprir critérios de saída:** commit `chore(sprint): complete Sprint 0 — setup phase`, atualizar este arquivo com marco, e avançar para Sprint 1 (CORE-001 como próximo ticket).

---

**Última atualização:** 2026-04-17 (CORE-001 completo — primeiro package real scaffolded)
