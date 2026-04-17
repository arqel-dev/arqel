# Ticket ativo

> Este arquivo é atualizado automaticamente após cada ticket completado.
> Serve como ponteiro para o Claude Code saber onde continuar.

## 🎯 Ticket corrente

**[GOV-001] SECURITY.md e processo de disclosure (expandir placeholder)**

**Fase:** 1 (MVP)
**Sprint:** 0 (Setup — paralelo)
**Prioridade:** P0
**Estimativa:** S
**Depende de:** INFRA-001 ✅

**Localização no planejamento:** `PLANNING/08-fase-1-mvp.md` §12 (GOV)

> **Sprint 0 INFRA completo** 🎉 — a continuação canónica é GOV-001 + GOV-003 (paralelos).

## 📋 Sprint 0 — Backlog sequencial

Ordem canónica (fonte: `PLANNING/08-fase-1-mvp.md` §2):

- [x] **INFRA-001** — Inicialização do monorepo Git ✅ 2026-04-17
- [x] **INFRA-002** — Configuração pnpm workspace + Composer path repositories ✅ 2026-04-17
- [x] **INFRA-003** — Configuração de ferramentas de formatação e lint (PHP e JS) ✅ 2026-04-17
- [x] **INFRA-004** — Configuração do pipeline de CI no GitHub Actions ✅ 2026-04-17
- [x] **INFRA-005** — Configuração de Renovate Bot + dependency grouping ✅ 2026-04-17

> **Nota:** a ordem em `CLAUDE.md` e `KICKOFF.md` divergia da canónica; a fonte é `PLANNING/08-fase-1-mvp.md` (ver regra de ouro #1 em `CLAUDE.md`).

## 📋 Paralelo ao Sprint 0

Pode ser trabalhado em paralelo após INFRA-001 pronto:

- [ ] **GOV-001** — SECURITY.md e processo de disclosure (placeholder criado em INFRA-001, a expandir)
- [ ] **GOV-003** — CONTRIBUTING.md + PR templates + DCO bot

## ✅ Completados

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

**Fase 1 MVP:** 5/123 tickets (4.1%)
**Sprint atual (Sprint 0):** 5/5 tickets (100%) — INFRA completo ✅
**Sprint 0 paralelo (GOV):** 0/2 tickets (GOV-001, GOV-003 pendentes)

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

**Última atualização:** 2026-04-17 (INFRA-005 completo — Sprint 0 INFRA 5/5)
