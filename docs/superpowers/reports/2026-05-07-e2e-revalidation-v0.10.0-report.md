# E2E re-validation report — Arqel v0.10.0

**Data:** 2026-05-07
**Versão testada:** v0.10.0 (Packagist + NPM)
**Ambiente:** `~/PhpstormProjects/arqel-test/` (in-place upgrade from v0.9.4)
**Spec:** [`docs/superpowers/specs/2026-05-07-v0.10.0-quality-foundation-design.md`](../specs/2026-05-07-v0.10.0-quality-foundation-design.md)
**Plan:** [`docs/superpowers/plans/2026-05-07-v0.10.0-quality-foundation.md`](../plans/2026-05-07-v0.10.0-quality-foundation.md)
**Antecessor:** [`2026-05-07-e2e-revalidation-v0.9.x-consolidated-report.md`](./2026-05-07-e2e-revalidation-v0.9.x-consolidated-report.md)

---

## Resumo executivo

Os 3 fixes da quality foundation v0.10.0 passaram todos no walkthrough externo. **Fase 1 do roadmap está oficialmente fechada.** BUG-VAL-007 (release pipeline framework) verificado pelo release workflow auto-publicar `arqel-dev/framework@0.10.0` no Packagist sem intervenção manual — primeira vez em 5 releases. BUG-VAL-011 (Form children) e BUG-VAL-012 (panel prefix) ambos confirmados em browser real. Um sub-bug latente novo descoberto (BUG-VAL-013, FlashContainer não montado no layout default) — não bloqueia v0.10.0, vai para backlog.

**Próximo:** v0.10.1 sprint dedicada a Playwright E2E smoke set, conforme decidido nos specs antecessores.

---

## Status dos 3 fixes desta sprint

| # | Bug | Resultado v0.10.0 | Notas |
|---|-----|-------------------|-------|
| 7 | **BUG-VAL-007** Release pipeline framework | ✅ PASS | Release workflow inclui job `Split PHP — framework: success`. `arqel-dev/framework@0.10.0` publicado no Packagist automaticamente. Sem manual subtree split (primeira vez). |
| 11 | **BUG-VAL-011** Form Section children | ✅ PASS | `/admin/posts/{id}/edit` renderiza inputs (TextField, slug, body, etc.) dentro das Sections "Content" e "Meta", não só headers. |
| 12 | **BUG-VAL-012** Panel prefix Save URL | ✅ PASS | Save persiste — `router.put` vai a `/admin/posts/{id}` correctamente. Mudança aparece em DB. |

---

## Status acumulado dos BUG-VAL bloqueantes (toda a Fase 1)

| # | Bug | Status final |
|---|-----|--------------|
| 1 | BUG-VAL-001 paginação preserva perPage | ✅ resolvido em v0.9.2 |
| 2 | BUG-VAL-002 row Edit/Delete dispatch | ✅ resolvido em v0.9.3 + v0.9.4 (Edit form completo agora funciona via 011+012) |
| 3 | BUG-VAL-003 bulk Delete selected | ✅ resolvido em v0.9.3 |
| 4 | BUG-VAL-004 clear search | ✅ resolvido em v0.9.2 |
| 7 | BUG-VAL-007 release pipeline framework | ✅ resolvido em v0.10.0 |
| 10 | BUG-VAL-010 stock action URL | ✅ resolvido em v0.9.3 + v0.9.4 |
| 11 | BUG-VAL-011 form Section children | ✅ resolvido em v0.10.0 |
| 12 | BUG-VAL-012 panel prefix em Edit/Create | ✅ resolvido em v0.10.0 |

**Veredicto:** todos os 8 BUG-VAL bloqueantes da Fase 1 estão fechados.

---

## Sub-bug descoberto durante walkthrough (novo)

### BUG-VAL-013 — FlashContainer não montado no layout default [P2, não-bloqueante]

- **Sintoma:** após Save em Edit page, request retorna 200/302 + flash `arqel::messages.flash.updated` no session, redirect para Edit page (intentional, Filament-like) — **mas nenhum toast/notification aparece**. Visualmente parece "nada acontecer".
- **Verificação:** `<FlashContainer>` existe em `packages-js/ui/src/flash/FlashContainer.tsx` e está exportado. Mas não está montado no layout shell (`resources/views/arqel/layout.blade.php` + `resources/js/app.tsx`).
- **Não-bloqueante:** funcionalidade backend correcta; só falta feedback visual.
- **Prioridade:** P2 (UX). Pode ser fix mecânico (montar `<FlashContainer>` no shell) ou parte de v0.10.1 E2E sprint que cobrirá Edit/Create paths.

---

## Status dos não-bloqueantes (continuam abertos)

| # | Bug | Estado |
|---|-----|--------|
| 5 | BUG-VAL-005 resource auto-discovery vestigial | OPEN — backlog |
| 6 | BUG-VAL-006 widget API ausente | OPEN — backlog |
| 8 | BUG-VAL-008 doctor false positive auth | OPEN — backlog |
| 9 | BUG-VAL-009 peer deps `@inertiajs/react` 2.x vs 3.x | OPEN — backlog |
| 13 | **BUG-VAL-013 FlashContainer não montado** | OPEN — descoberto nesta sprint |

---

## Operational concern descoberto durante upgrade do sandbox

`composer.json` em `arqel-test` tinha `"arqel-dev/framework": "^0.9.1"` que **não cruza minor** (caret SemVer permite 0.9.x mas não 0.10.x). `composer update 'arqel-dev/*'` reportou success mas mantive em 0.9.4. Workaround: `composer require arqel-dev/framework:^0.10.0 --no-update` antes do `composer update`.

**Implicação:** consumidores existentes em v0.9.x não recebem v0.10.0 automaticamente — precisam bump constraint manualmente. Documentar no CHANGELOG e/ou release notes do GitHub release.

---

## Sumário da sprint v0.10.0

### PRs

- **PR #20** — spec v0.10.0
- **PR #21** — plan v0.10.0
- **PR #22** — 3 fixes consolidados (release.yml + Form.php + InertiaDataBuilder + ResourceMeta + ArqelEditPage + ArqelCreatePage)
- **PR #23** — bump 0.9.4 → 0.10.0

### Tag

- **v0.10.0** publicada com **sucesso total automático** — release workflow inclui 20 jobs `Split PHP — *` (19 sub-packages + framework), publish-npm, create GitHub release. Tempo total ~3 min.

### Tests

- 3 novos Pest unit tests em `packages/form/tests/Unit/FormSchemaRecursiveSerializationTest.php`
- 2 novos Pest feature tests em `packages/core/tests/Feature/InertiaDataBuilderTest.php`
- Suite existente continua verde (`packages/form` 40 passed, `packages/core` 7 InertiaDataBuilderTest tests passed)

### Walkthrough

- 3/3 critérios passaram (BUG-VAL-007/011/012)
- 1 sub-bug novo identificado (BUG-VAL-013, P2)

---

## Veredicto final

**Fase 1 do roadmap está fechada.** Todos os 8 BUG-VAL bloqueantes resolvidos e validados externamente. v0.10.0 publicada sem manual workarounds.

**Próxima sprint = v0.10.1**, dedicada a Playwright E2E smoke set, conforme decidido no spec consolidado v0.9.x. Sem E2E, próxima cadeia de bugs latentes pode repetir-se.

**Backlog Fase 2 features** pode arrancar em paralelo com v0.10.1 — mas a recomendação é **bloquear features novas** até E2E ship, para evitar acumular novos caminhos não-testados.

### Próximos passos

1. **v0.10.1** — Playwright E2E smoke set (login, list, filter, sort, page, search, bulk delete, row delete, edit form render+save, create form render+save). Integrar como required CI check.
2. **Backlog regular Fase 2:**
   - BUG-VAL-005 — resource auto-discovery
   - BUG-VAL-006 — widget API
   - BUG-VAL-008 — doctor false positive
   - BUG-VAL-009 — peer deps
   - BUG-VAL-013 — FlashContainer mount
3. **Decisão de release:** futuras releases minor (0.10.x → 0.11.x) devem incluir nota explícita sobre constraint upgrade no consumer side.
