# BUG-VAL-014 resolution — `code_scanning` ruleset rule removed

**Data:** 2026-05-08
**Tipo:** Config-only (GitHub UI) — sem release necessária
**Spec antecessor:** N/A (fix imediato sem brainstorm formal)
**Relatório fonte:** [`2026-05-08-e2e-revalidation-v0.10.1-report.md`](./2026-05-08-e2e-revalidation-v0.10.1-report.md)

---

## Sumário

BUG-VAL-014 (descoberto na sprint v0.10.1) era um recurring blocker: branch protection ruleset main tinha rule `code_scanning` com `alerts_threshold: errors` exigindo CodeQL success. Mas `.github/workflows/security.yml` declara `codeql-js` e `codeql-php` jobs com `if: false` (skip explícito porque CodeQL/GHAS exigem repo público ou GHAS comprado, e Arqel é privado por agora). Resultado: SKIPPED ≠ SUCCESS → ruleset bloqueava merges.

**Resolução:** rule `code_scanning` removida do ruleset via GitHub UI (Settings → Rules → main ruleset → editar → desactivar Code scanning). Restantes 5 rules mantidas (`deletion`, `non_fast_forward`, `pull_request`, `required_status_checks`, `required_linear_history`).

**Verificação:**

```bash
$ gh api "repos/arqel-dev/arqel/rules/branches/main" --jq '.[] | .type'
deletion
non_fast_forward
pull_request
required_status_checks
required_linear_history
```

`code_scanning` ausente. Próximos PRs já não vão ser bloqueados pelo gating de CodeQL.

## Trade-off

CodeQL continua a correr advisory (jobs ainda existem em `security.yml`), mas qualquer findings são informativos, não gating. Para repo público, isto é aceitável — comunidade pode ler os reports CodeQL nos workflow runs.

## Decisão futura paralela

Abrir o repo Arqel como público activaria CodeQL grátis sem GHAS. Vantagens:
- CodeQL real gating sem custo
- Visibilidade pública alinha com a posição "open source MIT" do projeto
- Comunidade pode contribuir

Pré-requisitos antes de abrir:
- Security audit (secrets scan no histórico, sensitive paths)
- `CONTRIBUTING.md` polish + DCO documentação
- `SECURITY.md` (vulnerability disclosure process)
- README/landing page de qualidade

Não bloqueia Fase 2 do roadmap. Sprint dedicada quando o projeto estiver pronto para audiência pública.

## Status

| Item | Resultado |
|------|-----------|
| `code_scanning` rule removida do ruleset main | ✅ |
| Outras 5 rules mantidas (incluindo 4 required status checks) | ✅ |
| Próximos PRs não exigem admin merge bypass | ✅ (a verificar no próximo PR real) |
| Sem release/version bump necessária | ✅ (config-only) |

## Próximos passos

- **Validar empiricamente** no próximo PR (qualquer): merge deve ser `CLEAN`, não `BLOCKED`
- Decidir scope concreto da **Fase 2 do roadmap** via brainstorm dedicado (`PLANNING/09-fase-2-essenciais.md`)
- Considerar abrir repo público em sprint dedicada — não bloqueante
