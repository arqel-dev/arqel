# Arqel — Roadmap to 1.0

> **Documento vivo.** Gap analysis acionável rumo à v1.0.0 estável.
> Base: **v0.16.0** (publicado 2026-07-06 — npm + Packagist). Atualizado por rodadas do loop de qualidade.
> Última atualização: 2026-07-06 (rodada 5).

> **Changelog do documento**
> - **Rodada 5 (2026-07-06):** **Relation Managers ENTREGUE** (milestone 0.18, PR #356) — `RelationManager` + `Resource::relations()` + `RelationController` (8 endpoints) + React (`ResourceEditTabs`/`RelationManagerPanel`/modais) + dogfood + E2E (validado ao vivo). CRUD para HasMany/MorphMany, attach/detach para BelongsToMany; MorphTo/HasManyThrough deferidos a 0.18b. Lacuna competitiva #2 fechada → HAVE. Foco movido para 0.19 (Extensibilidade + Notifications).
> - **Rodada 4 (2026-07-06):** **v0.16.0 PUBLICADO** — minor release entregando Imports (`arqel/import`), UI de conta (UserMenu + Perfil, #333), e 4 fixes de segurança (marketplace SecurityScanner #352, autz por-resource dos endpoints AI #354, export CSV-injection #340, bulk-action authz #341). Milestones 0.16 (Perfil+gerador) e 0.17 (Imports) ambos entregues e publicados. 0.18 (Relation Managers) retomado nesta sessão (PR #356).
> - **Rodada 3 (2026-07-05):** **Imports ENTREGUE** (`arqel/import`, PR #346 feature + PR #349 hardening de 3 bugs). Lacuna competitiva #1 fechada → HAVE. Milestone 0.17 concluído. Foco movido para 0.18 (Relation Managers).
> - **Rodada 2 (2026-07-05):** Perfil de usuário fechado (PR #333 mergeado → HAVE); bug do gerador §2.3 corrigido (PR #342 mergeado); spec de Imports em revisão (PR #343). Milestone 0.16 concluído. Foco movido para 0.17 (Imports).
> - **Rodada 1 (2026-07-04):** primeira passada de gap analysis.

## Nota de contexto crítica

O roadmap canônico (`PLANNING/07-roadmap-fases.md:33`) define **v1.0.0 = fim da Fase 3**. Mas o código real já está em **v0.16.0** e **já embarca pacotes inteiros de Fase 2 e 3**: `tenant`, `audit`, `mcp`, `versioning`, `workflow`, `realtime`, `ai`, `marketplace`. O planejamento documentado está **dessincronizado do estado real** — o produto está bem além do que os docs de fase descrevem como pré-1.0.

Implicação: o critério de versionamento precisa ser reescrito antes de comunicar 1.0, senão a promessa de SemVer nasce inconsistente. **O bloqueio para 1.0 não é arquitetura nem features de fase — é ergonomia de API pública + um ADR de freeze.**

---

## 1. Paridade competitiva (Arqel vs Filament/Nova)

| Área | Status | Evidência | Nota |
|------|--------|-----------|------|
| Auth (login/logout/guards/painel protegido) | ✅ HAVE | `packages/auth/src/Routes.php:38`; `Panel.php:255` `authGuard()`; `EnsureUserCanAccessPanel.php` | Login, register, verify-email, reset-password, guard configurável. Skipa se host já tem rota `login` (Breeze/Fortify). |
| **Perfil/Conta de usuário** | ✅ HAVE | `Panel::profile()`/`profileEnabled()` gate; `ProfileController`; `Routes::registerProfile`; `UserMenu` dropdown (tema movido do Topbar); FormRequests name/email + password; i18n en+pt_BR | **Fechado no PR #333** (mergeado `61e0a11`, 2026-07-04). Opt-in via `Panel::profile()`. |
| Resources CRUD | ✅ HAVE | `ResourceController.php:49-115`; `Resource.php:307/332/393` | list/create/edit/view/delete com authz por ação. |
| **Relations na UI** | ✅ HAVE | `packages/core/src/Relations/RelationManager.php`; `RelationController.php` (8 endpoints); `packages-js/ui/src/relations/` (`ResourceEditTabs`, `RelationManagerPanel`, `RelationFormModal`, `AttachModal`) | **Fechado (milestone 0.18).** CRUD dedicado para HasMany/MorphMany + attach/detach para BelongsToMany, aba na página de edição, autz por Policy do model relacionado. `MorphTo`/`HasManyThrough` como RelationManager e os novos relation-*fields* ficam para 0.18b. |
| RBAC / Policies | ✅ HAVE | `ArqelGate.php:43`; `PolicyDiscovery.php`; `ResourceController.php:62/71/101/111` | Gate/Policy por Resource e por Action. Falta só UI de gestão de roles (Filament também delega a plugin). |
| **Notifications (database)** | 🟡 PARTIAL | Flash: `useFlash.ts:12`. **Database notifications: MISSING** (0 hits `DatabaseNotification`/`markAsRead`) | Flash OK. Falta o sino de notificações persistidas (read/unread). |
| **Global search (registros cross-resource)** | 🟡 PARTIAL | `NavigationCommandProvider.php:19` (navega p/ resources); `ArqelIndexPage.tsx:15` (search intra-tabela) | Command palette navega para resources; busca é intra-tabela. Falta spotlight de *registros* cross-resource (`getGloballySearchableAttributes`). |
| Bulk actions | ✅ HAVE | `BulkAction.php:16/26/33`; `Table.php:62` | Chunking + deselect. |
| **Imports** | ✅ HAVE | `packages/import/`: `Importer`, `ImportColumn`, `ProcessImportJob` (chunk + failed-rows CSV), `ImportAction`, upload/download controllers autorizados | **Fechado** (PR #346 feature + #349 hardening). CSV/XLSX async, skip+failed-rows CSV, mapeamento declarativo. Falta só: UI de mapeamento + `make:import` (fora do 0.17). |
| Exports | ✅ HAVE | `ExportAction.php:48`; `Exporters/{Csv,Xlsx,Pdf}`; `Jobs/ProcessExportJob.php` | Robusto, async. |
| Dashboards / Widgets | ✅ HAVE | `StatWidget.php`, `ChartWidget.php`, `TableWidget.php`, `CustomWidget.php`; `Dashboard.php`; filtros | Stats/charts/table/custom + dashboards com filtros. |
| Multi-tenancy | ✅ HAVE | `TenantManager.php`; `TenantScope.php:25`; resolvers Subdomain/Path/Header/Session/AuthUser; `Panel.php:262` | Forte: múltiplos resolvers, scope automático, switching, features por tenant. |
| **Plugin API registrável no Panel** | 🟡 PARTIAL | Marketplace existe (`marketplace/src/Models/Plugin.php`); `Panel.php` **não** expõe `->plugin()` (comentário `:19` remete a CORE-006, não feito) | Distribuição/instalação via marketplace OK; falta o contrato `Plugin::make()->register()` que injeta resources/pages/widgets in-code. |
| Fields (tipos) | ✅ HAVE | 21 em `fields/src/Types/` + 8 em `fields-advanced/src/Types/` = **29** | Core: Text…BelongsTo/HasMany. Advanced: RichText, Markdown, Code, KeyValue, Tags, Repeater, Builder, Wizard. |
| Table | ✅ HAVE | 11 colunas + filtros (Select…QueryBuilder) + sort/search/pagination + summaries | Paridade forte, incl. QueryBuilder filter. |
| Actions | ✅ HAVE | Row/Header/Toolbar/Bulk + `Confirmable.php:20` (type-to-confirm) + `HasForm.php` | Modais de confirmação + action forms. |

### Lacunas competitivas priorizadas

| # | Lacuna | Status | Peso p/ paridade | Nota |
|---|--------|--------|------------------|------|
| 1 | ~~**Imports (CSV/Excel)**~~ | ✅ **FECHADO (PR #346 + #349)** | Alto | Pacote `arqel/import` entregue: Importer/ImportColumn/ProcessImportJob/ImportAction + controllers. Follow-ups (0.18+): ownership do download, `max:` filesize, serialização do ImportAction. |
| 2 | ~~**Relation Managers**~~ | ✅ **FECHADO (milestone 0.18)** | Alto | Aba dedicada + CRUD HasMany/MorphMany + attach/detach BelongsToMany entregues. Follow-ups (0.18b): MorphTo/HasManyThrough como RelationManager, relation-*fields*, combobox pesquisável no AttachModal, UI de pivot fields. |
| 3 | ~~**Perfil/Conta de usuário**~~ | ✅ **FECHADO (PR #333)** | Médio-alto | Mergeado 2026-07-04. |
| 4 | **Database Notifications UI** | PARTIAL | Médio | Sino read/unread. |
| 5 | **Plugin API no Panel** | PARTIAL | Médio-alto | Diferencial-chave do Filament (extensibilidade in-code). |
| 6 | **Global Search de registros** | PARTIAL | Médio | Spotlight cross-resource. |

---

## 2. API-freeze readiness

### 2.1 Estado dos 18 ADRs

15 estáveis. **Em risco: ADR-007 (Base UI vs Radix — já flip-flopped em 2026-05, `03-adrs.md:275`) e ADR-014 (Filament-compatible — fonte de todas as divergências de naming abaixo).** ADR-013 (MCP) estável na impl mas com risco externo (standard MCP evoluindo).

### 2.2 Divergências doc↔código na superfície de API pública (a congelar)

| # | Contrato | Doc diz | Código faz | Severidade |
|---|----------|---------|------------|------------|
| **A** | **Factory de Field** | `Field::text('name')` (`05-api-php.md:266`; gerador `ResourceGenerator.php:241`) | ✅ **RESOLVIDO (PR #342).** O gerador agora emite `use Arqel\Fields\FieldFactory as Field;`; testes de resolução de nome adicionados (`ResourceGeneratorTest.php:111-146`). | ✅ Fechado (era 🔴 release-blocker) |
| B | Factory de Column | `Column::text('name')` (`05-api-php.md:108`) | Só `Column::make()`; testes usam `TextColumn::make()` | 🟠 Alta |
| C | Factory de Action | `Action::view()/delete()` (`05-api-php.md:120`) | `Action::make()` + variants em classe separada `Actions::view()` (`Actions.php:18`) | 🟠 Alta |
| D | Convenção BelongsToField | `belongsTo('role', RoleResource::class)` (nome da relação) | `make($name,…)` deriva relação removendo `_id`; testes usam `make('author_id',…)` | 🟠 Alta |
| E | Widget extension | override `stat()/description()/chart()` (`05-api-php.md:628`) | setters fluent `statDescription()/color()/chart()` (`StatWidget.php:108`); `ChartWidget` usa `chartData()/chartType()` | 🟠 Alta |
| F | `Resource::table/form/indexQuery` | tipado `Table`/`Form`/`Builder` (`05-api-php.md:104`) | retornam `mixed` (`Resource.php:167/184/200`) | 🟡 Média |
| G | `SharedProps.tenant` | `Tenant \| null` (`06-api-react.md:35`) | `unknown` (`types/src/inertia.ts:50`) | 🟡 Média |
| H | API de auth do Panel | não documentada | `login()/registration()/passwordReset()/emailVerification()` (`Panel.php:77-414`) | 🟡 Média (documentar antes de congelar) |

### 2.3 Bug confirmado (verificação adversarial) — Divergência A ✅ RESOLVIDO

> **Status (rodada 2):** corrigido no **PR #342** (`923f21b`, mergeado). O template do gerador
> passou a emitir `use Arqel\Fields\FieldFactory as Field;` e ganhou testes de resolução de
> nome (não só de string) — `ResourceGeneratorTest.php:111-146` — cobrindo o caso com fields,
> o placeholder comentado, e a garantia estrutural "todo `Field::` tem `use ... as Field`".
> O relato original permanece abaixo como registro.

`arqel:resource` (gerador) emitia código que **não rodava**:
- `ResourceGenerator.php:93` só emite `use Arqel\Core\Resources\Resource;` — **não importa `Field` nem `FieldFactory`**.
- `ResourceGenerator.php:241` emite `Field::{$type}('name')`.
- `Arqel\Fields\Field` é `abstract` sem `text()`/`__callStatic`; nenhum alias publica `Field`.
- O teste (`ResourceGeneratorTest.php:34`) só asserta a **string** do output, nunca executa o código gerado → **o teste mascara o defeito** (padrão "tests-mask-integration-gaps" dos loops anteriores).

**Convenção de facto (evidência do showcase — código que RODA):** a factory já existe e é consistente. Os Resources reais fazem:
```php
use Arqel\Fields\FieldFactory as Field;   // apps/showcase/.../SettingResource.php:10, TicketResource.php:10
use Arqel\Table\Columns\TextColumn;        // colunas via classe concreta ::make()
use Arqel\Actions\Actions;                 // actions via classe Actions::view()/edit()/delete()
```
Ou seja, `Field::text()` **funciona** desde que o arquivo tenha `use Arqel\Fields\FieldFactory as Field;`. **O bug do gerador é apenas a ausência desse `use` no template** — não é preciso decidir convenção nova nem `class_alias` global. Correção: adicionar o import ao template do gerador + um teste que compile/execute o output (não só assert-de-string).

Resultado para o usuário: hoje o Resource gerado dá erro de classe indefinida ao carregar. Fix é localizado no gerador. **Recomendação para a decisão de escopo #2: manter a convenção do showcase (`FieldFactory as Field` + classes concretas de Column/Action) e alinhar docs+gerador a ela** — em vez de introduzir aliases globais.

### 2.4 Recomendação de freeze

1. **Novo ADR-019 "API Freeze & SemVer commitment"**: define público vs `@internal`; compromisso SemVer estrito a partir de 1.0; decisão sobre `mixed` (Divergência F).
2. **Resolver a camada de factory (bloqueante)**: escolher e implementar a convenção canônica `Field::`/`Column::`/`Action::` (alias/facade) **ou** reescrever docs+gerador para `FieldFactory`/`TextColumn::make`/`Actions::`. Alinhar doc + gerador + testes.
3. Congelar convenção BelongsToField (D), contrato de Widget (E), tipos TS públicos (G), documentar auth do Panel (H).
4. Marcar ADR-007 e ADR-014 como **Final**.
5. Deprecation policy escrita (≥1 minor com `@deprecated` + aviso runtime; nunca remover em patch).
6. Sincronizar `07-roadmap-fases.md` com a realidade (v1.0 ≠ fim-Fase-3 hoje).

---

## 3. Cobertura docs/testes

### Testes com gap material
| Pacote | Src | Test | Ratio | Nota |
|--------|-----|------|-------|------|
| `fields-js` (JS) | 35 | 6 | **0.17** | Pior ratio; core de UI; muito abaixo do target JS ≥80%. |
| `ui` (JS) | 90 | 35 | 0.39 | Maior superfície do monorepo; risco de regressão alto. |
| `react` (JS) | 22 | 10 | 0.45 | Hooks/componentes críticos. |
| `fields-advanced` (JS) | 20 | 11 | 0.55 | |
| `table` (PHP) | 37 | 29 | 0.78 | Mais baixo entre os core PHP grandes (ainda ok). |
| `marketplace` (PHP) | 56 | 46 | 0.82 | Único PHP com test < src. |

Core PHP (56 src / 95 test) robusto. E2E: **34 specs** Playwright (showcase 23, demo 10, tenant 1) — cobre CRUD/tables/actions/tenancy/workflow/realtime/versioning/responsivo. Faltam E2E de auth-avançado, export, marketplace.

### Docs com gap material
- **`apps/docs/resources/*` são stubs** (`resource.md`, `table.md`, `fields.md`, `form.md`, `actions.md` marcados `stub — DOCS-005`): a referência de API principal está vazia.
- **Widgets sem página dedicada** (guide + reference/php).
- **`reference/php/` incompleto**: falta widgets, tenant, workflow, versioning, marketplace, realtime.
- **`i18n` e `realtime-collab` (JS) sem SKILL.md nem README.md** — únicos pacotes sem doc.
- READMEs ausentes: vários PHP (ai, audit, cli, export, marketplace, realtime, versioning, workflow) e JS (a11y, auth, versioning).
- Paridade de tradução a auditar (en 49 < pt-BR/es 74 arquivos).

---

## 4. Milestones propostos

| Milestone | Escopo | Destrava |
|-----------|--------|----------|
| **0.16 — Perfil + gerador** ✅ **CONCLUÍDO** | ✅ PR #333 mergeado (perfil); ✅ bug do gerador corrigido (PR #342, `FieldFactory as Field` no template) | Table-stakes + release-blocker de API |
| **0.17 — Imports** ✅ **CONCLUÍDO** | ✅ Pacote `arqel/import` (PR #346) + hardening de 3 bugs (PR #349) | Lacuna competitiva #1 |
| **0.18 — Relations** ✅ **CONCLUÍDO** | ✅ `RelationManager` + `Resource::relations()` + `RelationController` (8 endpoints) + UI React em abas; CRUD HasMany/MorphMany + attach/detach BelongsToMany | Lacuna competitiva #2 |
| **0.19 — Extensibilidade + Notifications** | Plugin API no Panel (`->plugin()`) + Database Notifications UI + Global Search de registros | Lacunas #4/#5/#6 |
| **0.20 — API-freeze prep** | ADR-019 + resolver divergências B–H + doc `resources/*` + fechar tipos TS | Pré-requisito duro de 1.0 |
| **0.9x — Hardening** | Cobertura fields-js/ui/react ao target; E2E auth/export/marketplace; piloto de produção | Confiança de estabilidade |
| **v1.0.0** | API congelada + SemVer commitment + docs completas | Estável |

---

## 5. Decisões de escopo

### Rodada 1 — resolvidas
1. ✅ **Merge do PR #333** — MERGEADO na main (`61e0a11`, 2026-07-04). Lacuna #3 (Perfil de usuário) fechada.
2. ✅ **Convenção de factory** — decisão: **investigar mais** → investigação concluída (§2.3). A convenção de facto do showcase (`FieldFactory as Field` + classes concretas de Column/Action) **funciona e é consistente**; o bug era só o gerador esquecer o `use`. Alinhado gerador à convenção do showcase (sem aliases globais). Fix localizado, não-breaking.
3. ✅ **Must-haves para 1.0** — decisão: **TODAS as 4 lacunas são must-have**: Imports (CSV/Excel), Relation Managers, Plugin API no Panel, Database Notifications + Global Search. (Milestones 0.17–0.19.)
4. ⏳ **ADR-019** (freeze + SemVer) + reescrita do critério de versionamento — pendente, milestone 0.20.

### Rodada 4 — estado de execução
- ✅ **Milestone 0.18 CONCLUÍDO:** Relation Managers entregues — `RelationManager` abstract (detecção de tipo hasMany/morphMany/belongsToMany, `pivotFields()` allowlist, `abilities()` fail-open de duas camadas) + `Resource::relations()` (default `[]`, zero regressão) + `RelationController` genérico com os 8 endpoints (index/create/store/edit/update/destroy/attach/detach), `{relation}` allowlisted (404) e `{related}` sempre escopado ao pai (anti-IDOR), attach/detach só em BelongsToMany (405 caso contrário). React: `ResourceEditTabs` (abas + `?tab=` na URL) + `RelationManagerPanel` + `RelationFormModal`/`AttachModal`. Dogfood no showcase + E2E Playwright. Follow-ups explícitos para 0.18b: MorphTo/HasManyThrough como RelationManager, novos relation-*fields*, combobox pesquisável no AttachModal (hoje input de id simples — `BelongsToInput` não é reusável sem dep circular `fields→ui`), UI de pivot fields (hoje `AttachModal` sempre envia `pivot: {}`), gate de fetch por tab-ativa (hoje eager no mount via Radix `TabsContent`), toast de erro em refetch silencioso pós-mutação.
- **Próximo:** 0.19 (Plugin API no Panel + Database Notifications UI + Global Search de registros).

### Rodada 3 — estado de execução
- ✅ **Milestone 0.17 CONCLUÍDO:** pacote `arqel/import` entregue (PR #346) + hardening de 3 bugs vivos na main (PR #349: upload disk-root path, `.txt`→500, guard de header requiredMapping). Nota: o pacote foi implementado em paralelo por duas sessões do loop (#346 mergeado, #347 duplicado fechado); só os 3 fixes reais do review de #347 foram portados via #349.
- ⏳ **ADR-019** segue pendente (0.20).
- **Próximo:** 0.18 (Relation Managers, lacuna #2).

### Rodada 2 — estado de execução
- ✅ **Milestone 0.16 concluído:** decisão #1 (Perfil) e decisão #2 (gerador) ambas entregues (PR #333 + PR #342).
- ⏳ **ADR-019** pendente (0.20).

### Fila de trabalho derivada (prioridade por destravar 1.0)
- ~~**0.16:** gerador ✅ / Perfil ✅~~ — **concluído**.
- ~~**0.17:** Imports 1ª classe~~ ✅ **CONCLUÍDO** (PR #346 + #349). Follow-ups: ownership do download, `max:` filesize, serialização do ImportAction.
- ~~**0.18:** Relation Managers~~ ✅ **CONCLUÍDO**. Follow-ups (0.18b): MorphTo/HasManyThrough, relation-*fields*, combobox pesquisável no AttachModal, UI de pivot fields, gate de fetch por tab-ativa, toast de erro em refetch.
- **0.19 (próximo):** Plugin API no Panel + Database Notifications + Global Search de registros.
- **Contínuo:** cobertura de testes fields-js/ui/react; docs `resources/*` (DOCS-005); Layout/UX (Eixo C).
- **0.20:** ADR-019 + resolver divergências B–H + fechar tipos TS.
