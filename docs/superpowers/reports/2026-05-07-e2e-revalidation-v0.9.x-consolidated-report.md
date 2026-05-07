# E2E re-validation report — Arqel v0.9.1 → v0.9.4 (consolidated)

**Data:** 2026-05-07
**Versões testadas:** v0.9.1, v0.9.2, v0.9.3, v0.9.4 (Packagist + NPM)
**Ambiente:** `~/PhpstormProjects/arqel-test/` (Laravel 13.7, PHP 8.4.11, SQLite)
**Specs:**
- [`2026-05-06-fresh-laravel-e2e-validation-design.md`](../specs/2026-05-06-fresh-laravel-e2e-validation-design.md)
- [`2026-05-06-external-validation-mcp-integration-design.md`](../specs/2026-05-06-external-validation-mcp-integration-design.md)
- [`2026-05-08-v0.9.2-ui-hotfix-design.md`](../specs/2026-05-08-v0.9.2-ui-hotfix-design.md)
- [`2026-05-07-v0.9.3-actions-url-resolution-design.md`](../specs/2026-05-07-v0.9.3-actions-url-resolution-design.md)
- v0.9.4 (sem spec formal — fix mínimo de 1 linha)

---

## Resumo executivo

Quatro sprints de hotfix em cadeia (v0.9.1 → v0.9.2 → v0.9.3 → v0.9.4) abordaram bugs **descobertos pela validação externa** em `~/PhpstormProjects/arqel-test/`. Cada sprint corrigiu uma camada e expôs a seguinte. O CRUD UI é mais frágil do que os testes do monorepo sugeriam — o caminho de read (list, filter, search, sort, pagination, bulk delete) está agora **funcional**, mas o caminho de **write** (create/edit forms) ainda tem 2 bugs novos (BUG-VAL-011 e BUG-VAL-012) descobertos no último walkthrough.

**Decisão:** parar sprints táticas de hotfix e tratar Fase 2 do roadmap com prioridade explícita aos 2 bugs novos + débito técnico revelado pela cadeia. Continuar a fazer "fix → tag → walkthrough" em sprints sucessivas é sinal de uma camada que precisa de **trabalho de design**, não outra patch.

---

## Status acumulado dos BUG-VAL

### Bloqueantes da Fase 2 do roadmap (descobertos em v0.9.1)

| # | Bug | v0.9.1 | v0.9.2 | v0.9.3 | v0.9.4 | Notas |
|---|-----|--------|--------|--------|--------|-------|
| 1 | BUG-VAL-001 paginação preserva perPage | ❌ FAIL | ✅ PASS | ✅ PASS | ✅ PASS | Fix em `ArqelIndexPage.tsx` (perPage state local) |
| 2 | BUG-VAL-002 row Edit/Delete | ❌ FAIL | 🟡 botões aparecem mas 404 | 🟡 ainda 404 | 🟡 navega mas form vazio + save 404 | Cascata: render → URL stock → URL placeholder → **BUG-VAL-011 + 012** |
| 3 | BUG-VAL-003 bulk Delete selected | ❌ FAIL | 🟡 botão aparece mas 404 | ✅ PASS | ✅ PASS | Fix em duas etapas (UI + bulk route) |
| 4 | BUG-VAL-004 clear search | ❌ FAIL | ✅ PASS | ✅ PASS | ✅ PASS | Fix em `buildQuery` (`if !== undefined`) |

**Veredicto:** 3/4 bloqueantes resolvidos. BUG-VAL-002 está 50% — botões renderizam, URL row navega, mas o **form de edit/create é o próximo bug em cascata**.

### Não-bloqueantes (continuam abertos)

| # | Bug | Estado |
|---|-----|--------|
| 5 | BUG-VAL-005 resource auto-discovery vestigial | OPEN — backlog Fase 2 |
| 6 | BUG-VAL-006 widget API ausente | OPEN — backlog Fase 2 |
| 7 | BUG-VAL-007 release pipeline framework subtree | OPEN — **primeiro ticket Fase 2** (manual workaround usado em v0.9.1, v0.9.2, v0.9.3, v0.9.4 — insustentável) |
| 8 | BUG-VAL-008 doctor false positive auth starter kit | OPEN — backlog |
| 9 | BUG-VAL-009 peer deps `@inertiajs/react` 2.x vs 3.x | OPEN — backlog |
| 10 | BUG-VAL-010 stock action URL/method ausente | ✅ resolved em v0.9.3 (split em 0.9.4) |

### Novos bugs descobertos no walkthrough v0.9.4

| # | Bug | Severidade | Descrição |
|---|-----|-----------|-----------|
| 11 | **BUG-VAL-011 Section serialization perde children** | P0 | `PostResource::form()` declara `Section::make('Content')->schema([fields])`. Backend serialize emite a Section como entry layout mas **com `entry.schema = []` vazio** — children não aparecem. `<FormRenderer>` walka schema e não encontra fields para renderizar → form de edit/create renderiza headers de section sem inputs. |
| 12 | **BUG-VAL-012 Edit/Create page falta panel prefix** | P0 | `packages-js/ui/src/pages/ArqelEditPage.tsx:46` faz `router.put(\`/${slug}/${id}\`, ...)` (URL relativa). Resolve para `/posts/1`, **falta `/admin/`**. Mesma issue em `ArqelCreatePage.tsx:46` para `router.post`. → 404 ao salvar. |

Ambos descobertos em 2026-05-07 walkthrough v0.9.4. Ambos pré-existem desde v0.9.0+ — só foram alcançáveis depois que botões Edit/Delete começaram a renderizar (v0.9.2) e suas URLs corrigidas (v0.9.4).

---

## Sumário das sprints

### v0.9.2 — UI hotfix (bridge server-shape ↔ component-shape)

PR #11 (4 fixes) + PR #12 (bump). Tag `v0.9.2`.

- `ArqelIndexPage.tsx`: 4 mudanças coordenadas
  - perPage local state hidratado de `props.pagination.perPage`
  - `actions.row` mapeado para `rowActions` render-prop via `<ActionMenu>`
  - `actions.bulk` mapeado para `bulkActions` ReactNode + `selectedIds` state
  - `buildQuery` emite `search=""` explícito quando vazio
- 7 testes Vitest novos
- Walkthrough revelou que botões aparecem mas dispatch retorna 404 (causa: stock Actions sem URL)

### v0.9.3 — Action URL resolution + bulk dispatch

PR #15 (3 fixes PHP + 9 testes Pest) + PR #16 (bump). Tag `v0.9.3`.

- `Action::toArray($user, $record, $resource)` — 3rd arg duck-typed
- `resolveStockUrl()` retorna `[url, method]` para 5 stock variants
- `InertiaDataBuilder::serializeMany($resource)` propaga
- `POST admin/{resource}/bulk/{action}` rota nova + `ResourceController::bulkAction()` com fallback `whereIn->delete()`
- 9 testes Pest scope-limited
- Walkthrough: bulk OK, row ainda 404 (causa: stock URL retornava `null` quando record null)

### v0.9.4 — Row URL placeholder

PR #17 (1 fix simplification) + PR #18 (bump). Tag `v0.9.4`.

- `resolveStockUrl` simplificado: `$idSegment = $id !== null ? $id : '{id}'`. Sempre emite URL.
- Frontend (`invokeAction` em `ArqelIndexPage.tsx`) já fazia `replace('{id}', recordId)` per click. Caminho fechado.
- 1 test Pest novo (placeholder)
- Walkthrough: row navega, mas Edit page renderiza form vazio E save retorna 404 → BUG-VAL-011, BUG-VAL-012

### BUG-VAL-007 — release pipeline framework subtree (workaround manual)

`release.yml` matrix omite o meta-package `framework`. Em **todas as 4 releases** desta sprint (v0.9.1, 0.9.2, 0.9.3, 0.9.4) foi necessário fazer subtree split + tag-only push manual ao repo derivativo `arqel-dev/framework`. Receita estável, mas insustentável a longo prazo. **Primeiro ticket obrigatório da Fase 2.**

---

## Lições e padrão da cadeia

A sequência v0.9.1 → 0.9.4 é instrutiva:

1. **v0.9.1 → 0.9.2**: bug de **render UI** — frontend não consumia o shape do server
2. **v0.9.2 → 0.9.3**: bug de **dispatch URL** — server não emitia URL
3. **v0.9.3 → 0.9.4**: bug de **placeholder substitution** — URL emitida sem placeholder, falhava em table-level serialisation
4. **v0.9.4 → ???**: bugs de **form schema** + **panel prefix** — caminho create/edit nunca foi testado fim-a-fim externamente

Cada camada falhou no primeiro contacto externo. Os testes feature do monorepo (`apps/demo`) **passam** todos os critérios via `php artisan test` (rotas existem, payload tem shape correcto, validation roda) mas **não exercitam a UI**. Os testes Vitest só cobrem o `ArqelIndexPage` adapter — não tocam Edit/Create pages, não exercitam form schema serialization, não tocam panel prefix resolution.

**Conclusão metodológica:** o framework precisa de **smoke tests E2E em browser** (Playwright) ou pelo menos **testes Inertia full-stack** (Laravel `assertInertia` que verifica props completos) **antes** de declarar uma camada estável. Sprint dedicada de E2E está nos não-objectivos do spec antecessor (`2026-05-06-fresh-laravel-e2e-validation-design.md`), agora torna-se prioridade.

---

## Performance observations

- 4 sprints de hotfix → 4 ciclos completos PR + bump + tag + manual framework split + sandbox upgrade + walkthrough
- Cada ciclo: ~3-5 horas de execução (subagent + CI + walkthrough)
- Total: ~15 horas + vários reset/upgrade do sandbox
- Output v0.9.4: 248 tests Vitest pass, 56 actions Pest pass, 284+ core Pest pass, NPM + Packagist verificáveis

---

## Veredicto final

**Fase 2 do roadmap NÃO está aberta.** Bloqueado por:

1. **BUG-VAL-011** (Section children serialization) — bloqueia create/edit forms
2. **BUG-VAL-012** (panel prefix faltando em ArqelEditPage/CreatePage) — bloqueia save de qualquer form
3. **BUG-VAL-007** (release pipeline framework subtree) — bloqueia disciplina de release a longo prazo

Recomendação: **Sprint Fase 2 inicial dedicada a 4 itens** (em vez de outra v0.9.x):

1. Resolver BUG-VAL-007 — patch `release.yml` matrix (curto, ~1 hora)
2. Resolver BUG-VAL-012 — adicionar panel prefix em ArqelEditPage + CreatePage (mecânico, ~30 min)
3. Resolver BUG-VAL-011 — investigar serialização de Section children (médio, design + fix; ~2-4 horas)
4. **Adicionar Playwright E2E smoke** cobrindo: login, list, filter, sort, page, search, bulk delete, **row delete**, **edit form render + save**, **create form render + save**. Sem isto, próxima cadeia repete.

Tag resultante: v0.10.0 (minor bump por causa do scope, não patch).

Backlog regular: BUG-VAL-005, 006, 008, 009.

---

## Anexo — commits e PRs

### Monorepo `arqel-dev/arqel` (main)

```
af8ff64 chore(release): bump 0.9.3 -> 0.9.4
b20d064 fix(actions): emit {id} placeholder for row stock URLs at table level
c192042 chore(release): bump 0.9.2 -> 0.9.3
afe945a feat(core): add POST {resource}/bulk/{action} route + controller
67f2094 fix(core): propagate Resource to action serialisation
49bf586 fix(actions): resolve stock URLs/methods in Action::toArray
c5963d3 docs(docs): add v0.9.3 actions URL resolution implementation plan
... (specs e plans de v0.9.2/0.9.3 inclusos)
7507e13 chore(release): bump 0.9.1 -> 0.9.2
deadb86 fix(ui): emit empty search param to clear the server-side filter
991abd5 fix(ui): map server actions.bulk to toolbar bulk actions + selection state
d9318c9 fix(ui): map server actions.row to rowActions render-prop
41e82cb test(ui): cover filter + search preserving perPage
4139ca7 fix(ui): preserve perPage across pagination/filter/search handlers
4cd6534 chore(release): bump 0.9.0 -> 0.9.1
9368042 chore(deps): regenerate pnpm-lock.yaml after demo-old removal
98a6aef docs(docs): add external validation + MCP integration impl plan
cf018f8 docs(docs): add external validation + MCP integration spec
2a23761 fix(ui): wrap filters in filter[name] query string for TableQueryBuilder
6815885 fix(ui): wire ArqelIndexPage to Inertia router for filters/search/sort/pagination
0c1c9ed fix(auth): wire HandleArqelInertiaRequests into bundled auth routes
...
```

PRs merged: #3 (rebase walkthrough), #4 (bump 0.9.1), #7 (initial validation report), #8 (current.md), #9 (v0.9.2 spec), #10 (v0.9.2 plan), #11 (v0.9.2 hotfix), #12 (bump 0.9.2), #13 (v0.9.3 spec), #14 (v0.9.3 plan), #15 (v0.9.3 hotfix), #16 (bump 0.9.3), #17 (v0.9.4 hotfix), #18 (bump 0.9.4).

### Sandbox externo `~/PhpstormProjects/arqel-test/`

```
cae02ea chore: upgrade to arqel-dev/framework v0.9.4 + verify row URL placeholder fix
ace4b3e chore: upgrade to arqel-dev/framework v0.9.3 + verify BUG-VAL-010 fix
02f54aa chore: upgrade to arqel-dev/framework v0.9.2 + verify 4 BUG-VAL hotfixes
... (commits originais Fase 1+2 do plano de validação externa)
```

11 commits no sandbox, 0 GitHub remotes (sandbox local permanente conforme plano original).
