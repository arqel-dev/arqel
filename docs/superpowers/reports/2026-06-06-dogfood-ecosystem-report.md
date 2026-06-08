# Relatório do Loop de Dogfooding do Ecossistema Arqel

**Data:** 2026-06-06 a 2026-06-08
**Baseline:** `apps/showcase` (app que exercita os 20 pacotes PHP + 18 pacotes JS)
**Resultado:** **CONVERGIU** — 2 rondas limpas consecutivas (Round 20 + Round 21)
**Total:** **89 bugs de framework corrigidos** + **1 hardening de segurança**, ao longo de 21 rondas de detecção

---

## 1. Sumário executivo

Um loop autónomo multi-agente detectou, verificou adversarialmente, corrigiu (TDD), revisou e fez merge (com CI CLEAN obrigatório) de **89 bugs de framework** distintos no ecossistema Arqel, mais **1 hardening de segurança** (information disclosure) surgido na revisão automática de commits. O loop parou quando atingiu o critério predefinido de **2 rondas de detecção limpas consecutivas**.

Cada bug seguiu o pipeline: detecção cega por cluster de domínio → dedup + filtro contra um registry persistente de assinaturas → verificação adversarial (refutar primeiro; classificar framework-bug vs app-misuse vs not-a-bug) → issue no GitHub → fix TDD (teste falha-antes/passa-depois) em worktree git isolado → Pint + PHPStan level max → PR → merge em CI CLEAN → atualização do registry.

**Restrição respeitada:** zero gasto real — apenas stub providers de AI, sem LLM/Stripe/cloud reais. A tag `v0.13.0` **não foi cortada** (é confirmação manual humana).

---

## 2. Curva de detecção (bugs confirmados por ronda)

| Ronda | Bugs | Notas |
|---|---|---|
| R1 | 13 | Setup inicial + FieldRegistry split (blocker), envelopes PHP↔React |
| R2 | 8 | bulk-actions/export, AI, table, versioning |
| R3 | 6 | nullable, tenant resolver, AiCache, widget keys |
| R4 | 7 | **família authz**: Gate::has-vs-Policy (versioning/realtime/widgets) |
| R5 | 7 | field-level write authz, collab, AI effectiveFields, locale, XLSX |
| R6 | 5 | layout canSee, collab slug, MCP, sidebar viewAny |
| R7 | 8 | widget generator (blocker), readonly/disabled persist, upload/delete authz |
| R8 | 5 | authGuard, row-action URLs, relationship sort, file ACL |
| R9 | 6 | file edit-save, **CSS injection (segurança)**, export data-leak, email-verify guard |
| R10 | 6 | primeira ronda sem high/blocker — tudo medium/low |
| R11 | 1 | rotas arqel.actions.* mortas removidas |
| R12 | 2 | audit read-authz (HIGH), Column/Filter canSee morto |
| R13 | 5 | versioning cast-corruption (HIGH), command-palette path, ... |
| R14 | 2 | validation-layout-visibility, widget filter non-scalar |
| R15 | 4 | BelongsTo searchRoute (HIGH), SelectField options, AI 500, Column getState — **+ 1 hardening AI** |
| R16 | 1 | action form fields não serializados (HIGH) |
| **R17** | **0** | **primeira ronda limpa** |
| R18 | 1 | export DateColumn mode/format |
| R19 | 1 | repeater/builder/wizard nested schema (HIGH) |
| **R20** | **0** | **ronda limpa** |
| **R21** | **0** | **ronda limpa → 2 consecutivas → CONVERGÊNCIA** |

A curva (13→8→6→7→7→5→8→5→6→6→1→2→5→2→4→1→**0**→1→1→**0**→**0**) mostra o esgotamento progressivo: a cauda longa de bugs sistémicos foi varrida, e os últimos achados foram cantos-finais ou camadas-seguintes de fixes anteriores.

---

## 3. Distribuição por pacote (bugs corrigidos)

| Pacote | Bugs | | Pacote | Bugs |
|---|---|---|---|---|
| core | 15 | | export | 4 |
| fields | 11 | | actions | 4 |
| widgets | 10 | | tenant | 4 |
| ai | 8 | | workflow | 4 |
| versioning | 6 | | mcp | 3 |
| table | 5 | | ui | 2 |
| realtime | 5 | | audit | 2 |
| auth | 4 | | form | 1 |
| | | | fields-advanced | 1 |

**Issues GitHub:** #45 a #221 (numeração não-contígua; PRs de fix #46 a #222).

---

## 4. Famílias sistémicas (o valor real do exercício)

Os 89 bugs não foram aleatórios — agruparam-se em **famílias sistémicas** recorrentes, cada uma varrida ao longo de múltiplas rondas e múltiplos entry points:

1. **Contratos PHP↔React (key/envelope mismatch)** — cada lado construía à mão a forma esperada pelo outro, e os testes mascaravam a divergência (cada lado testava a sua própria forma). Ex.: envelope de widget, alinhamento de coluna, keys de polling, badge colors. *Rondas 1-3.*

2. **Autorização não aplicada em todos os entry points** — `Gate::has` vs `Policy`, predicados de visibilidade mortos, endpoints sem gate. Varrida em: CRUD de resource, canEdit/canSee de field, canSee de layout, upload/delete/search, sidebar + command-palette viewAny, collab REST+WS, versioning history/restore, widget dashboard, audit read-endpoints. *Rondas 4-7, 12.*

3. **API documentada mas nunca ligada no entry point de serialização real ("documented-but-unwired")** — a família mais persistente. Um builder/config/predicado público existe e está documentado, mas o seu payload completo nunca é produzido onde o React o lê. CORE-006/CORE-010 ficaram parcialmente por completar. Varrida em: BelongsToField searchRoute, SelectField optionsRelationship, Column getState/formatState, action form fields, nested repeater/builder/wizard schema, export DateColumn mode/format. *Rondas 13, 15, 16, 18, 19.*

4. **Testes mascaram lacunas de integração** — testes que fixam a forma quebrada como contrato (ex.: `resolveOptions()===[]`, `[{name,type}]`), escondendo o bug até um teste de integração real o expor.

5. **Morph-class FQCN-vs-alias** — `getMorphClass()` (alias) vs FQCN cru sob `enforceMorphMap`. *Rondas 2, 10, 13.*

6. **Corrupção de dados cast-aware** — snapshot raw vs cast (versioning double-encoding). *Ronda 13 (HIGH).*

7. **Segurança** — CSS injection (R9), information disclosure de erro upstream de AI (R15 hardening). Ambas tratadas com rigor (allowlist por slot; `report()` + mensagem genérica preservando mensagens de limite).

**Meta-observação (rondas 12-19):** quase todos os bugs tardios foram a **camada-seguinte exposta por um fix anterior** (#181 reverteu um veredito antigo; #190 paralelo a #72; #198 completou #115; #199 seguiu #189; #217 irmão do #206; #221 irmão do #204/#206/#213) ou o **canto-final de uma família já varrida**. Isto é o assinatura de convergência: cada correção descasca a próxima camada até não restar nenhuma.

---

## 5. Refutações (rigor adversarial)

7 candidatos foram **refutados** na verificação adversarial (não eram bugs de framework), provando que o gate funcionou:

- `widgets::tablewidget-empty-columns` (R1) — contrato duck-typed documentado.
- `fields::fileupload-mimetypes-extensions` (R4) — app-misuse: `acceptedFileTypes()` recebe MIME types, não extensões.
- `nav::navigationitem-missing-admin-prefix` (R4) — código morto/unwired; a sidebar real prefixa corretamente.
- `audit::activity-log-no-tenant-scope-no-authz` (R5) — *parcialmente revertido no R12*: o veredito app-misuse original foi corrigido quando o #181 confirmou o gap de authz nos endpoints de leitura.
- `audit::logsactivity-logs-all-attributes` (R5) — anti-pattern documentado (override obrigatório).
- `fields::unique-no-update-ignore` (R5) — DEFERRED a CORE-006 (workaround documentado).
- `ai::embed-cost-not-recorded` (R7) — estruturalmente impossível (embed devolve vetor cru, sem dados de custo).

---

## 6. Garantias de qualidade

- **TDD em todos os fixes:** teste regressivo que falha-antes/passa-depois.
- **CI CLEAN obrigatório:** matriz PHP 8.3/8.4 × Laravel 12/13 × mysql/postgres + Vitest + Playwright E2E + Biome + Pint + PHPStan level max + CodeQL + audits.
- **Worktrees isolados** por fix (sem conflitos paralelos).
- **DCO + Conventional Commits** em todos os commits.
- **Flakes de infra** (Docker Hub registry timeout, cli-ink Vitest) tratados via rerun, nunca contornados.

---

## 7. Estado da release

A release **v0.13.0** está **PREPARADA** no PR #185 (não merged):
- CHANGELOG consolidado em `## [0.13.0] - 2026-06-07` com todos os fixes (rondas 0-19) + subsecção `### Security`.
- Todos os 38 pacotes (20 PHP + 18 JS) bumped 0.12.0 → 0.13.0.
- CI CLEAN.

⚠️ **A tag `v0.13.0` NÃO foi cortada** — o push da tag dispara a publicação pública em npm/Packagist e é o passo manual do mantenedor:
```bash
git tag v0.13.0 && git push origin v0.13.0
```

---

## 8. Artefactos

- **Registry persistente:** `docs/superpowers/reports/dogfood-seen.json` (96 assinaturas: 89 fixed, 4 not-a-bug, 3 app-misuse).
- **Relatórios por ronda:** `docs/superpowers/reports/2026-06-06-dogfood-round-[1-21]-detection.json`.
- **Memórias por ronda:** `~/.claude/.../memory/project_dogfood_round[1-10]_bugs.md` + ponteiro consolidado em `MEMORY.md`.
- **Issues GitHub:** #45-#221. **PRs de fix:** #46-#222.
