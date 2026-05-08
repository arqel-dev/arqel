# E2E re-validation report — Arqel v0.11.0

**Data:** 2026-05-08
**Versão testada:** v0.11.0 (Packagist + NPM)
**Ambiente:** monorepo CI on `apps/demo` + `~/PhpstormProjects/arqel-test/` upgrade in-place
**Spec:** [`docs/superpowers/specs/2026-05-08-v0.11.0-widgets-frontend-design.md`](../specs/2026-05-08-v0.11.0-widgets-frontend-design.md)
**Antecessor:** [`docs/superpowers/reports/2026-05-08-e2e-revalidation-v0.10.1-report.md`](./2026-05-08-e2e-revalidation-v0.10.1-report.md)

---

## Resumo executivo

v0.11.0 ship-ed **Dashboard frontend integration end-to-end**. `ArqelDashboardPage` Inertia entry binds the production-ready `arqel-dev/widgets` PHP package (146 Pest tests) to the existing React widget components in `@arqel-dev/ui/widgets` (1020 LOC, Recharts). `arqel-dev/widgets` é agora parte do metapackage `arqel-dev/framework` — **BUG-VAL-006 oficialmente fechado** para consumers externos. E2E smoke set passa a 11 cenários incluindo dashboard regression-protect. **Fase 2 sprint 1 (WIDGETS) fechada.**

## Status

| Item | Resultado |
|------|-----------|
| `ArqelDashboardPage` Inertia entry implementado | ✅ |
| `arqel-dev/widgets` em `arqel-dev/framework` `require` | ✅ |
| `apps/demo` `MainDashboard` renderiza end-to-end (manual smoke) | ✅ |
| 11 cenários E2E pass localmente + CI | ✅ |
| Release workflow auto-publica framework + widgets sub-packages | ✅ |
| Sandbox `arqel-test` upgraded in-place (commit `74bf74f`) | ✅ |
| `composer require arqel-dev/framework:^0.11.0` resolve widgets transitively | ✅ |

## Cobertura E2E (11 cenários após v0.11.0)

| # | Spec | Categoria |
|---|------|-----------|
| 1+2 | login válido + inválido | auth |
| 3 | list (25 records) | resource read |
| 4 | filter (SelectFilter) | resource read |
| 5 | sort (column toggle asc/desc) | resource read |
| 6 | search + clear | resource read (BUG-VAL-004 regression) |
| 7 | pagination preserva perPage | resource read (BUG-VAL-001) |
| 8 | row Edit (form + save persist) | resource write (BUG-VAL-002+011+012) |
| 9 | row Delete (modal + confirm) | resource write |
| 10 | bulk Delete | resource write (BUG-VAL-003) |
| 11 | **dashboard renders 3 widgets** | **dashboard (NEW)** |

## Bugs resolvidos nesta sprint

- **BUG-VAL-006** — widget API ausente em consumers externos. Resolvido pela inclusão de `arqel-dev/widgets` no metapackage `arqel-dev/framework` `require`.

## Plan deviations descobertos durante implementação

Documentados aqui para retrospetiva metodológica:

1. **`apps/demo/composer.json` framework constraint bump 0.8.1 → 0.10.1.** Necessário para path repo poder pular `arqel-dev/widgets`. Não estava no plan, mas surfaced durante implementação. Limpo, scope-confined.

2. **`packages/core/routes/arqel.php` `dashboards` add to `$reservedSlugs` regex.** A polymorphic `admin/{resource}/{id}` route was matching `/admin/dashboards/main` first. Discovered by smoke test antes do E2E spec. One-liner fix; coberto pelos 146 Pest tests (não regrediu). **Lesson:** futuras rotas dedicadas sob `/admin/*` precisam ser explicitamente reservadas.

3. **`MainDashboard::recent_posts` initial column shape errado.** Plan-spec usou arrays simples `[['name' => 'title', ...]]`, mas `TableWidget::serialiseColumns()` só aceita objects with `toArray()` (ex: `Arqel\Table\Columns\TextColumn`). Surfaced no manual smoke (table renderizou sem rows). Corrigido para usar `TextColumn::make()`, `BadgeColumn::make()`, `DateColumn::make()` antes do E2E spec lock. **Lesson:** widget docs/SKILL.md devem clarificar este contract.

## Status acumulado dos BUG-VAL bloqueantes (toda a Fase 1 + early Fase 2)

| # | Bug | Status |
|---|-----|--------|
| 1 | paginação | ✅ v0.9.2 |
| 2 | row Edit/Delete | ✅ v0.9.3+v0.9.4+v0.10.0 |
| 3 | bulk Delete | ✅ v0.9.3 |
| 4 | clear search | ✅ v0.9.2 |
| 6 | **widget API ausente** | ✅ **v0.11.0** |
| 7 | release pipeline framework | ✅ v0.10.0 |
| 10 | stock action URL | ✅ v0.9.3+v0.9.4 |
| 11 | form Section children | ✅ v0.10.0 |
| 12 | panel prefix | ✅ v0.10.0 |
| 14 | code_scanning ruleset blocks | ✅ (config-only fix) |

## Backlog não-bloqueante (continua aberto)

| # | Bug | Estado |
|---|-----|--------|
| 5 | resource auto-discovery vestigial | OPEN |
| 8 | doctor false positive auth starter kit | OPEN |
| 9 | peer deps `@inertiajs/react` 2.x vs 3.x | OPEN |
| 13 | `<FlashContainer>` não montado no layout default | OPEN |

## Veredicto

**Fase 2 sprint 1 (WIDGETS) fechada com sucesso.** 5/14 BUG-VAL ainda no backlog (todos não-bloqueantes). Próxima sprint Fase 2 candidata via brainstorm dedicado:

- TENANT (multi-tenancy SaaS, 15 tickets)
- FIELDS-ADV (8 advanced field types, 20 tickets)
- TABLE-V2 (virtual scrolling, inline editing, 10 tickets)
- EXPORT (CSV/Excel/PDF, 7 tickets)
- CMDPAL (extend command palette, 5 tickets)
- AUDIT (audit log, 4 tickets)
- MCP-enhance (10 tickets)
- Backlog cleanup (BUG-VAL-005, 008, 009, 013)

Roadmap canónico em `PLANNING/09-fase-2-essenciais.md`.

## Próximos passos

- Decidir scope da próxima sprint via brainstorm
- Backlog regular: 4 BUG-VAL não-bloqueantes acima
- Cadência: cada feature nova ship com pelo menos 1 E2E spec adicional (precedente estabelecido em v0.10.1, mantido em v0.11.0)
