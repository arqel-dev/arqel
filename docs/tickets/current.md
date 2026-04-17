# Ticket ativo

> Este arquivo é atualizado automaticamente após cada ticket completado.
> Serve como ponteiro para o Claude Code saber onde continuar.

## 🎯 Ticket corrente

**[INFRA-002] Configuração pnpm workspace + Composer path repositories**

**Fase:** 1 (MVP)
**Sprint:** 0 (Setup)
**Prioridade:** P0 (blocker)
**Estimativa:** S (2-8h)
**Depende de:** INFRA-001 ✅

**Localização no planejamento:** `PLANNING/08-fase-1-mvp.md` §2 (INFRA-002)

## 📋 Sprint 0 — Backlog sequencial

Ordem canónica (fonte: `PLANNING/08-fase-1-mvp.md` §2):

- [x] **INFRA-001** — Inicialização do monorepo Git ✅ 2026-04-17
- [ ] **INFRA-002** — Configuração pnpm workspace + Composer path repositories ← ATIVO
- [ ] **INFRA-003** — Configuração de ferramentas de formatação e lint (PHP e JS)
- [ ] **INFRA-004** — Configuração do pipeline de CI no GitHub Actions
- [ ] **INFRA-005** — Configuração de Renovate Bot + dependency grouping

> **Nota:** a ordem em `CLAUDE.md` e `KICKOFF.md` divergia da canónica; a fonte é `PLANNING/08-fase-1-mvp.md` (ver regra de ouro #1 em `CLAUDE.md`).

## 📋 Paralelo ao Sprint 0

Pode ser trabalhado em paralelo após INFRA-001 pronto:

- [ ] **GOV-001** — SECURITY.md e processo de disclosure (placeholder criado em INFRA-001, a expandir)
- [ ] **GOV-003** — CONTRIBUTING.md + PR templates + DCO bot

## ✅ Completados

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

**Fase 1 MVP:** 1/123 tickets (0.8%)
**Sprint atual (Sprint 0):** 1/5 tickets (20%)

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

**Última atualização:** 2026-04-17 (INFRA-001 completo)
