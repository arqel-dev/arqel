# Ticket ativo

> Este arquivo é atualizado automaticamente após cada ticket completado.
> Serve como ponteiro para o Claude Code saber onde continuar.

## 🎯 Ticket corrente

**[INFRA-001] Criar monorepo com pnpm workspaces + composer path repositories**

**Fase:** 1 (MVP)
**Sprint:** 0 (Setup)
**Prioridade:** P0 (blocker)
**Estimativa:** M (1-3 dias)

**Localização no planejamento:** `PLANNING/08-fase-1-mvp.md` §2 (seção INFRA)

## 📋 Sprint 0 — Backlog sequencial

Ordem exata (tickets são sequencialmente dependentes):

- [ ] **INFRA-001** — Criar monorepo com pnpm workspaces + composer path repositories ← ATIVO
- [ ] **INFRA-002** — Configurar TypeScript base config + Vite + tsup
- [ ] **INFRA-003** — CI GitHub Actions com matrix PHP 8.3/8.4 × Laravel 12/13
- [ ] **INFRA-004** — Release pipeline (splitsh/lite + npm publish)
- [ ] **INFRA-005** — Pre-commit hooks (Husky + lint-staged)

## 📋 Paralelo ao Sprint 0

Pode ser trabalhado em paralelo após INFRA-001 pronto:

- [ ] **GOV-001** — SECURITY.md e processo de disclosure
- [ ] **GOV-003** — CONTRIBUTING.md + PR templates + DCO bot

## ✅ Completados

(vazio — primeiro ticket)

## 📊 Progresso geral

**Fase 1 MVP:** 0/123 tickets (0%)
**Sprint atual (Sprint 0):** 0/5 tickets (0%)

## 🔄 Ao completar o ticket ativo

O Claude Code deve:

1. Marcar checkbox [x] acima
2. Mover entry para seção "✅ Completados" com data
3. Atualizar "Ticket corrente" para próximo na sequência
4. Incrementar contadores de progresso
5. Commit este arquivo junto com o código: `chore(tickets): complete INFRA-001, start INFRA-002`

## 🚦 Critérios de saída Sprint 0

Todos os 5 tickets INFRA completos + verificação:

- [ ] `git clone` + `./scripts/init.sh` resulta em repo funcional
- [ ] `pnpm test:all` passa (mesmo com poucos testes reais ainda)
- [ ] CI roda e passa em PR mock
- [ ] `./scripts/release.mjs --dry-run` executa sem erro
- [ ] Pre-commit hook bloqueia commit com lint errors

**Ao cumprir critérios de saída:** commit `chore(sprint): complete Sprint 0 — setup phase`, atualizar este arquivo com marco, e avançar para Sprint 1 (CORE-001 como próximo ticket).

---

**Última atualização:** 2026-04-17 (inicial)
