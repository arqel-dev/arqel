# E2E re-validation report — Arqel v0.10.1

**Data:** 2026-05-08
**Versão testada:** v0.10.1 (Packagist + NPM)
**Ambiente:** `~/PhpstormProjects/arqel-test/` (in-place upgrade from v0.10.0) + monorepo CI on `apps/demo`
**Spec:** [`docs/superpowers/specs/2026-05-08-v0.10.1-playwright-e2e-design.md`](../specs/2026-05-08-v0.10.1-playwright-e2e-design.md)
**Antecessor:** [`docs/superpowers/reports/2026-05-07-e2e-revalidation-v0.10.0-report.md`](./2026-05-07-e2e-revalidation-v0.10.0-report.md)

---

## Resumo executivo

v0.10.1 ship Playwright E2E smoke set (10 cenários) e novo required CI check `Tests E2E (Playwright)` na branch protection da `main`. Todos os caminhos críticos onde a cadeia v0.9.x descobriu bugs latentes estão agora cobertos automaticamente. **Fase 2 features podem arrancar.**

## Status

| Item | Resultado |
|------|-----------|
| 10 cenários E2E rodam localmente em ~3-5 min | ✅ |
| 10 cenários E2E passam em CI (PR #27 + PR #28) | ✅ |
| `Tests E2E (Playwright)` é required check em main (4º) | ✅ |
| Sandbox `arqel-test` upgraded para v0.10.1 in-place sem incidente | ✅ (`b9973c8`) |
| Release pipeline auto-publica framework (BUG-VAL-007 fix mantém-se) | ✅ |

## Cobertura

| # | Spec | BUG-VAL relevante |
|---|------|-------------------|
| 1+2 | login válido + inválido | — |
| 3 | list (25 records) | — |
| 4 | filter (SelectFilter) | — |
| 5 | sort (column toggle asc/desc) | — |
| 6 | search + clear | BUG-VAL-004 |
| 7 | pagination preserva perPage | BUG-VAL-001 |
| 8 | row Edit (form + save persist) | BUG-VAL-002, 011, 012 |
| 9 | row Delete (modal + confirm) | — |
| 10 | bulk Delete (select + bulk action) | BUG-VAL-003 |

## Achados operacionais durante a sprint

### Stale workspace build (lesson learned)

Primeiro pass do subagent reportou bugs UI que não existiam: row Edit/Delete buttons não renderizavam em `apps/demo`. Causa real: `packages-js/ui/dist/*.js` estava stale relativamente aos fixes de v0.10.0. `pnpm -r --if-present run build` resolveu.

**Implicação metodológica:** qualquer trabalho E2E em `apps/demo` deve começar com rebuild full do workspace, não confiar no estado actual do `dist/`. Adicionar como pre-flight no SKILL.md ou contributing guide é recomendado para sprint posterior.

### `code_scanning` rule blocked merge

Branch protection ruleset (não classic protection rules) tem regra `code_scanning` que rejeita PRs com CodeQL `SKIPPED` (a config exige `alerts_threshold: errors`, mas SKIPPED não satisfaz). PR #27 ficou bloqueado mesmo com 21 checks SUCCESS. Resolvido com **admin merge bypass** desta vez.

**Próximo:** decidir scope da rule — desactivar `code_scanning` no ruleset (CodeQL continua a correr mas não gating) OU forçar CodeQL a não fazer skip. Goes para backlog como **BUG-VAL-014** abaixo.

## Bug novo descoberto

### BUG-VAL-014 — `code_scanning` ruleset blocks PRs when CodeQL skips [P2, recurring]

- **Sintoma:** branch protection ruleset main has `code_scanning` rule with `alerts_threshold: errors`. CodeQL workflows return `SKIPPED` quando o PR não toca paths relevantes. SKIPPED ≠ SUCCESS → rule blocks merge mesmo com restantes checks verdes.
- **Workaround usado:** admin merge bypass (`gh pr merge --admin` ou via UI "merge without waiting for requirements").
- **Fix options:**
  - (a) Remover `code_scanning` do ruleset (CodeQL still runs, but advisory)
  - (b) Configurar CodeQL para correr sempre (não SKIPPED)
  - (c) Mudar `alerts_threshold` para algo que aceite SKIPPED como pass
- **Prioridade:** P2. Vai impedir todo merge subsequente até resolver.

## Status acumulado dos BUG-VAL bloqueantes

Todos resolvidos:

| # | Bug | Resolvido em |
|---|-----|--------------|
| 1 | BUG-VAL-001 paginação | v0.9.2 |
| 2 | BUG-VAL-002 row actions | v0.9.3 + v0.9.4 + v0.10.0 |
| 3 | BUG-VAL-003 bulk Delete | v0.9.3 |
| 4 | BUG-VAL-004 clear search | v0.9.2 |
| 7 | BUG-VAL-007 release pipeline | v0.10.0 (verified again v0.10.1) |
| 10 | BUG-VAL-010 stock action URL | v0.9.3 + v0.9.4 |
| 11 | BUG-VAL-011 form Section children | v0.10.0 |
| 12 | BUG-VAL-012 panel prefix | v0.10.0 |

## Backlog não-bloqueante (continua aberto)

| # | Bug | Estado |
|---|-----|--------|
| 5 | BUG-VAL-005 resource auto-discovery | OPEN |
| 6 | BUG-VAL-006 widget API ausente | OPEN |
| 8 | BUG-VAL-008 doctor false positive | OPEN |
| 9 | BUG-VAL-009 peer deps `@inertiajs/react` | OPEN |
| 13 | BUG-VAL-013 FlashContainer não montado | OPEN |
| 14 | **BUG-VAL-014 code_scanning ruleset blocks** | **OPEN — recurring** (admin merge needed every PR until fixed) |

## Veredicto

**Fase 1 ironada e protegida. Fase 2 features podem arrancar.**

Cadência futura:
- Cada PR roda os 4 required checks (incluindo E2E)
- Novos features que tocam UI devem incluir spec E2E adicional ou ajustar existente
- Se nova cadeia de bugs aparecer, é sinal de cobertura insuficiente — aumentar smoke set, não disable

## Próximos passos

- **BUG-VAL-014** deve ser primeiro ticket Fase 2 (ou imediato hotfix v0.10.2) — admin merge a cada PR é insustentável
- Roadmap canónico: `PLANNING/09-fase-2-essenciais.md`
- Backlog regular: BUG-VAL-005, 006, 008, 009, 013
