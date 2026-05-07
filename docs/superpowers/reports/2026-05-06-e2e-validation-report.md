# E2E validation report — Arqel framework v0.9.1

**Data:** 2026-05-06 (walkthrough conduzido em 2026-05-07 UTC)
**Versão testada:** v0.9.1 (Packagist + NPM, public registries)
**Ambiente:** `~/PhpstormProjects/arqel-test/` — Laravel 13.7, PHP 8.4.11, SQLite, Node 22, pnpm 10.33
**Spec:** [`docs/superpowers/specs/2026-05-06-external-validation-mcp-integration-design.md`](../specs/2026-05-06-external-validation-mcp-integration-design.md)
**Plano:** [`docs/superpowers/plans/2026-05-06-external-validation-mcp-integration.md`](../plans/2026-05-06-external-validation-mcp-integration.md)

---

## Resumo executivo

A validação externa do framework Arqel v0.9.1 numa Laravel 13 limpa, instalado via Packagist real (`composer require arqel-dev/framework:^0.9.1`), **expôs lacunas materiais no caminho UI da Fase 1 MVP** que os testes feature do monorepo não capturavam.

- ✅ **Distribuição** funciona: Packagist + NPM resolvem v0.9.1, install corre clean, doctor reporta saudável, MCP server opera.
- ✅ **Backend** funciona: routing, auth, validation, persistence, tests verdes (8/8 dos resources, 9/10 com 1 ExampleTest pre-existente irrelevante).
- ❌ **UI da table** tem 3 falhas reproduzíveis (paginação, clear search, action buttons inexistentes na lista).
- ⚠️ **3 gaps de framework descobertos** que merecem ticket próprio (auto-discovery, widget API, release pipeline framework).

**Veredicto:** **Fase 1 MVP NÃO está pronta para Fase 2 do roadmap.** As falhas UI são bloqueantes — o framework anuncia CRUD admin panel e o "U" e "D" não funcionam pela UI no consumidor externo. Backend persiste correctamente (tests provam), mas o UX não expõe as actions.

---

## Critério-por-critério (15 walkthrough criteria)

| # | Critério | Resultado | Notas |
|---|----------|-----------|-------|
| 1 | Login com credenciais válidas | ✅ PASS | admin@arqel.test/password autentica |
| 2 | Login com credenciais inválidas exibe erro | ✅ PASS | Mensagem de erro visível |
| 3 | Index `/admin/posts` lista records paginados | ✅ PASS | 25 posts mostrados |
| 4 | SelectFilter `status=published` filtra a lista | ✅ PASS | |
| 5 | TernaryFilter `featured` filtra a lista | ✅ PASS | |
| 6 | Paginação navega entre páginas | ❌ **FAIL** | Botões Next/Prev não navegam — provavelmente handler não wired ao Inertia router |
| 7 | Form de create renderiza | ⚠️ N/A | Botão "Create" ausente da UI (ver #11) |
| 8 | Validation errors no submit inválido | ✅ PASS (backend) | Validation funciona via `php artisan test` mas inalcançável pela UI sem botão Create |
| 9 | Edit pré-popula dados | ⚠️ N/A | Botão "Edit" ausente da UI (ver #11) |
| 10 | Delete exibe modal de confirmação | ⚠️ N/A | Botão "Delete" ausente da UI (ver #11) |
| 11 | Bulk delete | ❌ **FAIL** | Selecção em massa visível, mas a action "Delete bulk" não está exposta na toolbar |
| 12 | Theme toggle dark/light | ✅ PASS | |
| 13 | Command Palette via Cmd+K | ✅ PASS | Navega entre resources |
| 14 | Search global filtra | ⚠️ PARTIAL | Search funciona, mas **clear** não restaura a lista |
| 15 | Sort por coluna | ✅ PASS | |

**Bonus checks (este projeto adiciona Categories):**

- ✅ `/admin/categories` lista 5 categorias seeded
- ✅ Nav group "Content" agrupa Posts + Categories
- ✅ Category soft delete + slug auto-gen (testado via factory)

**Score:** 11/15 ✅ + 1 partial + 3 bloqueantes na camada UI das row actions.

---

## Smoke MCP (Fase 3)

`@arqel-dev/mcp-server@0.9.1` instalado via `npx -y` no `.mcp.json` do projeto externo, com walk-up automatic do `process.cwd()` localizando o Laravel root. Smoke conduzido em sessão Claude Code separada apontando para `~/PhpstormProjects/arqel-test`.

| Tool | Resultado |
|------|-----------|
| `search_docs` | ✅ |
| `get_adr` | ✅ |
| `get_api_reference` | ✅ |
| `list_resources` | ✅ — UserResource, PostResource, CategoryResource detectados |
| `describe_resource` | ✅ |
| `generate_resource` | ✅ |
| `generate_field` | ✅ |

**Veredicto MCP:** todas as 7 tools funcionam. Sem regressões.

---

## Bugs descobertos

Cada um deve virar ticket no `PLANNING/`. Numerados para referência cruzada com este relatório.

### BUG-VAL-001 — UI table: paginação não navega [P0, bloqueante]

- **Sintoma:** click em "Next" / "Prev" não muda a lista
- **Esperado:** Inertia router visit com `?page=N`, lista re-renderiza com novo offset
- **Provável causa:** handler `onPageChange` não está wired ao `router.get` em `<ResourceIndex>` para Posts (ou wiring quebrou após o fix `effa873` ter mudado para `?filter[*]=v` syntax)
- **Reprodução:** /admin/posts com 25 records, per_page padrão. Click "Next" — nada acontece.
- **Localização suspeita:** `packages-js/ui/src/resource/ResourceIndex.tsx` ou pagination component

### BUG-VAL-002 — UI table: row actions ausentes (Edit, Delete) [P0, bloqueante]

- **Sintoma:** colunas `actions` (`Actions::edit()`, `Actions::delete()`) declaradas no PostResource não renderizam botões na lista
- **Esperado:** cada row tem botões Edit + Delete (ou kebab menu) que linkam para `/admin/posts/{id}/edit` e disparam delete
- **Provável causa:** `Table::actions([...])` não está a serializar para o React side, OU o React side não está a renderizar `RowActionsCell`
- **Reprodução:** /admin/posts → inspeccionar qualquer row; sem botões action visíveis. Mas `php artisan route:list` mostra rotas existem.
- **Impacto:** torna Update e Delete inalcançáveis pelo UX, embora persistam correctamente via POST/PATCH directo (tests provam)

### BUG-VAL-003 — UI table: bulk action button ausente [P0, bloqueante]

- **Sintoma:** selecção em massa (checkboxes) é visível, mas a barra de bulk actions (com botão "Delete") não aparece após selecionar rows
- **Esperado:** após selecionar 1+ rows, toolbar mostra "Delete X selected"
- **Provável causa:** mesma serialização que `BUG-VAL-002` — `bulkActions([Actions::deleteBulk()])` declarado mas não consumido pelo React
- **Reprodução:** /admin/posts → checkar 2-3 rows → toolbar não muda

### BUG-VAL-004 — UI table: clear search não restaura [P1]

- **Sintoma:** apagar conteúdo do search input não dispara visit que limpa o filtro de search
- **Esperado:** `?search=` (vazio) faz a lista voltar a mostrar tudo
- **Provável causa:** debounce em `handleSearchChange` (300ms) corre, mas `buildQuery()` em `ArqelIndexPage.tsx:50-62` faz `if (params.search) data['search'] = params.search;` — string vazia é falsy, omite o param, mas o backend mantém o estado anterior porque a URL não tem `search=` para invalidar. Solução: enviar `search=` (vazio) explicitamente para limpar, OU emitir uma flag clear
- **Reprodução:** /admin/posts → digitar "test" no search (lista filtra) → Backspace tudo → lista permanece filtrada

### BUG-VAL-005 — Resource auto-discovery não funciona [P1]

- **Sintoma:** colocar `PostResource` em `app/Arqel/Resources/` (path declarado em `config/arqel.php`) não regista o resource. Acesso a `/admin/posts` retorna 404 até hand-register em `ArqelServiceProvider`.
- **Causa:** `ResourceRegistry::discover()` (ou equivalente) nunca é chamado do framework boot path. As config keys `resources.path` + `resources.namespace` são vestigiais.
- **Workaround:** apps adicionam manualmente `PanelRegistry::resources([\App\Arqel\Resources\PostResource::class, ...]);` em `ArqelServiceProvider::boot()`.
- **Decisão:** ou implementar o discoverer, ou remover/documentar as config keys como decorativas

### BUG-VAL-006 — Widget API ausente em v0.9.1 [P2]

- **Sintoma:** `arqel:install` cria `app/Arqel/Widgets/` (pasta vazia, sugerindo convenção reservada) mas nenhum base class `Widget` ou `StatCard` é shipped pelo framework metapackage
- **Verificação:** `vendor/arqel-dev/core/src/Widgets/` não existe; `arqel-dev/widgets` não está em `arqel-dev/framework` require list; `grep -r "class StatCard" vendor/arqel-dev/` empty
- **Decisão:** ou ship `StatCard` em `core`, ou adicionar `arqel-dev/widgets` ao metapackage, ou remover a criação da pasta no install até a API existir

### BUG-VAL-007 — Release pipeline: framework subtree não na matrix [P1]

- **Sintoma:** `tag v0.9.1` push disparou release workflow que processou 19 sub-packages mas **não** o meta-package `framework`. Resultado: Packagist serviu `arqel-dev/{core,...}@v0.9.1` mas `arqel-dev/framework` ficou em v0.9.0 até intervenção manual
- **Causa:** `.github/workflows/release.yml` tem matrix de 19 packages explícita; `framework` (sourced de `packages/arqel/`) está omitido. Histórico de v0.8.0/0.8.1/0.9.0 sugere que foi sempre split manualmente
- **Mitigação aplicada:** subtree split + tag-only push manual de `arqel-dev/framework@v0.9.1` (commit SHA `3a3c049` no repo derivativo)
- **Fix necessário:** adicionar `framework` (com `prefix=packages/arqel`) à matrix do release.yml. Próximo bump (v0.9.2 ou v0.10.0) deve incluir esse fix.

### BUG-VAL-008 — Doctor: false positive sobre auth starter kit [P3]

- **Sintoma:** `arqel:doctor` reporta "auth.starter_kit_detected — No Laravel auth starter kit detected. Arqel does not ship login/register pages — install Breeze, Jetstream, or Fortify."
- **Realidade:** Arqel **ships** `/admin/login` via `Arqel\Auth\LoginController` (verificado retornando 200 OK). A diretiva está incorreta.
- **Fix:** remover/reescrever este check, ou detectar o `Arqel\Auth\LoginController` e mudar o veredicto para info.

### BUG-VAL-009 — pnpm peer dep `@inertiajs/react` 2.x vs 3.x [P3]

- **Sintoma:** após `arqel:install`, pnpm avisa que vários `@arqel-dev/*` packages declaram `@inertiajs/react@^2.0.0` peer mas a versão instalada é 3.1.0
- **Impacto:** build passa, runtime parece OK, mas warning é ruído de onboarding e bloqueia pnpm v10 strict mode em futuras versões
- **Fix:** bump peer ranges para `^2 || ^3` (ou `^3` se 2.x for incompatível) nos pacotes `@arqel-dev/{auth, fields, hooks, react, ui, types}`

---

## Performance observations

| Operação | Tempo | Notas |
|----------|-------|-------|
| `composer require arqel-dev/framework` | ~30s | 10 packages baixados; rede + lockfile |
| `arqel:install` | ~45s | Inclui pnpm install + shadcn init + stub copy |
| `pnpm run build` (cold) | 3.95s | vite 7.3.2; manifest + app-*.js (541KB) + app-*.css (85.77KB) |
| Primeira request `/admin/login` | ~500ms | Cold |
| `vendor/arqel-dev/` size | ~8 MB | 7 sub-packages |
| Test suite (10 tests) | ~1.2s | PHPUnit + RefreshDatabase |

Vite warning informativo: "Some chunks are larger than 500 kB". Não-bloqueante mas vale considerar code-splitting em fases futuras.

---

## Conclusão

**Pronto para Fase 2 do roadmap?** **Não. Bloqueado em BUG-VAL-001, 002, 003.**

A v0.9.1 entrega o caminho de distribuição + integração funcional (composer/npm/MCP), mas a UI da table CRUD, que é a feature *headline* do framework, falha em três pontos visíveis a qualquer utilizador no primeiro minuto: paginação, row actions, bulk actions. Estas falhas não são teóricas — são reproduzíveis no `arqel-test` e o utilizador não consegue Editar nem Eliminar um post pela UI, embora possa criar um novo via API.

A boa notícia: backend está sólido (tests passam), distribuição funciona, MCP integration é completa. Os bugs UI estão concentrados num único componente lógico (`<ResourceIndex>` / `Table` serialization), o que sugere fix focado e testável.

**Recomendação:** abrir uma sprint de hotfix UI (BUG-VAL-001/002/003/004) → tag v0.9.2 → repetir Fase 2-4 deste plano para reconfirmar antes de seguir para Fase 2 do roadmap maior.

**Pendências paralelas (não bloqueantes da reabertura):**
- BUG-VAL-007 (release pipeline framework) deve ser fix na próxima sprint para evitar manual subtree split repetido
- BUG-VAL-005, 006, 008, 009 podem ir para o backlog regular

---

## Anexo — Commits desta validação

**Monorepo (`arqel-dev/arqel`, branch `main`):**

```
4cd6534 chore(release): bump 0.9.0 -> 0.9.1
9368042 chore(deps): regenerate pnpm-lock.yaml after demo-old removal
98a6aef docs(docs): add external validation + MCP integration impl plan
cf018f8 docs(docs): add external validation + MCP integration spec
2a23761 fix(ui): wrap filters in filter[name] query string for TableQueryBuilder
6815885 fix(ui): wire ArqelIndexPage to Inertia router for filters/search/sort/pagination
0c1c9ed fix(auth): wire HandleArqelInertiaRequests into bundled auth routes
... (13 commits total da PR #3 + 1 PR #4 bump)
```

PR #3 (rebase merge), PR #4 (bump). Tag `v0.9.1` publicada.

**Projeto externo (`~/PhpstormProjects/arqel-test/`, local sandbox, sem GitHub):**

```
c5963d3 chore: add .mcp.json for @arqel-dev/mcp-server v0.9.1
c1896b7 feat: add CategoryResource + feature tests
e071c99 feat: add Category model + relate Post belongsTo Category
cb16fb9 feat: add PostResource showcase + feature tests
3864e20 feat: add Post model, migration, factory, seeder
7b148fe feat: arqel:install bootstrap
0a91254 chore: install arqel-dev/framework v0.9.1
66092aa chore: laravel skeleton baseline
```

8 commits, ~5500 LOC adicionadas (~3500 são auto-generated do `arqel:install`).
