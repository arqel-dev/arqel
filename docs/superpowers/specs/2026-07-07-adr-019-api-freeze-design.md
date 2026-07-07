# Design — ADR-019 (API Freeze & SemVer) + alinhamento de doc↔código

> **Preparação de API-freeze rumo a v1.0.** Roadmap §2 (API-freeze readiness).
> Status: design aprovado (brainstorming 2026-07-07). Próximo: plano de implementação.

## Contexto

O roadmap-to-1.0 identifica o bloqueio real para comunicar 1.0: **não é arquitetura nem
features de fase — é ergonomia de API pública + um ADR de freeze** (§ "Nota de contexto
crítica"). O §2.2 lista 8 divergências doc↔código na superfície pública (A resolvida no
#342; B-H abertas), e o §2.4 recomenda um novo ADR-019 formalizando o compromisso de API.

Investigação desta preparação (decisão de escopo aprovada): **todas as divergências B-H são
"a doc está errada, o código é a fonte da verdade"** — nenhuma exige mudança de runtime. Em
particular, a divergência F (`Resource::table()/form()/indexQuery()` retornam `mixed`) é
**design intencional**, não bug: o docblock de `Resource.php:182` explica que `arqel-dev/core`
não pode depender de `arqel-dev/table`/`form` (dependência circular — `table`/`form` já
dependem de `core`); o controller faz duck-typing do resultado. Tipar quebraria a arquitetura.

Portanto este trabalho é **zero-mudança-de-runtime**: um ADR de política + alinhamento da doc
de planejamento ao código real. A doc **pública** (`apps/docs/reference/php/*`) já está
correta (ex.: `apps/docs/pt-BR/reference/php/table.md:26` já diz `Column::make()`); apenas o
spec de planejamento interno (`PLANNING/05-api-php.md`, `PLANNING/06-api-react.md`) diverge.

## Decisões de escopo (aprovadas)

1. **Escopo:** política (ADR-019) + alinhamento de doc para B-H. **Sem mudança de código de
   runtime.**
2. **Convenção de factory canônica:** aceitar a convenção de facto que já roda (evidência no
   showcase) — `FieldFactory as Field` para fields, classes concretas para colunas
   (`TextColumn::make()`), `Actions::edit()` para actions. A doc alinha a isso. A
   inconsistência Field-vs-Column é documentada como intencional (Field tem alias-factory;
   Column/Action usam classes concretas), não "corrigida".
3. **Divergência F (`mixed`):** manter — é desacoplamento intencional. Documentar como tal.
4. **Divergência G (`SharedProps.tenant` = `unknown`):** decidir tipar como `Tenant | null`
   em follow-up (é TypeScript; requer um host com toolchain JS — bloqueado no host atual).
5. **ADR-007 e ADR-014:** já são `Aceite`; o ADR-019 declara que ambos estão **congelados sob este compromisso de freeze** (deixam de estar "em risco" no §2.1). Não se inventa um estado "Final" novo — o vocabulário de Estado dos ADRs é `Proposto`/`Aceite`/`Rejeitado`/`Superseded`.

## Componentes

### 1. ADR-019 em `PLANNING/03-adrs.md`

Novo ADR seguindo o formato dos ADRs 001-018 (título `## ADR-019: …`, Status, Contexto,
Decisão, Consequências). Conteúdo:

- **Superfície pública vs `@internal`:** o público é o documentado em
  `apps/docs/reference` — `Resource`, `FieldFactory`/`Field`, as classes concretas de Column,
  `Actions`, `Action` base, `Panel`, os `*Widget`, os builders `Table`/`Form`, `RelationManager`,
  `ImportAction`/`ExportAction`, `Importer`. Tudo o mais (`Support/`, `Http/Controllers`,
  `*Serializer`, `*QueryBuilder`) é `@internal`: pode mudar em minor sem aviso.
- **Compromisso SemVer a partir de 1.0:** breaking de API pública só em major; aditivo em
  minor; fix em patch. (Antes de 1.0, minors podem quebrar — o estado atual.)
- **Política de deprecação:** ≥1 minor com `@deprecated` (docblock; aviso runtime quando
  viável) antes de remover; **nunca** remover em patch.
- **Decisões sobre as divergências (registro):** B/C/D/E/H = doc alinhada ao código (código é
  a verdade); F = `mixed` mantido por desacoplamento; G = tipar em follow-up.
- **ADR-007 (Base UI vs Radix) e ADR-014 (Filament-compatible naming):** declarados congelados sob este compromisso (ambos já `Aceite`; deixam de ser "em risco").
- Nota: `PLANNING/07-roadmap-fases.md` (que define v1.0 = fim-Fase-3) precisa ser reescrito —
  o produto já embarca pacotes de Fase 2/3. Fora do escopo deste ADR (é um follow-up de doc),
  mas registrado como consequência.

### 2. Alinhamento de `PLANNING/05-api-php.md`

Edições pontuais de exemplo/texto (o código é a fonte da verdade):

- **B (Column):** `Column::text('name')` → `TextColumn::make('name')` (e demais `Column::<tipo>`
  → a classe concreta `::make()`).
- **C (Action):** `Action::view()/delete()` → `Actions::view()/delete()`; `Action::make()`
  permanece para actions custom.
- **D (BelongsToField):** `belongsTo('role', RoleResource::class)` → `belongsTo('role_id',
  RoleResource::class)`, com nota de que o nome da relação é derivado removendo o sufixo `_id`.
- **E (Widget):** `stat()/description()/chart()` (override) → setters fluent
  `statDescription()/color()`; `ChartWidget` usa `chartData()/chartType()`.
- **F (table/form/indexQuery):** documentar o retorno `mixed` como intencional (desacoplamento),
  citando o docblock de `Resource.php`.
- **H (auth do Panel):** adicionar a API `login()/registration()/passwordReset()/
  emailVerification()` do `Panel` (`Panel.php:77-414`), hoje ausente da doc.

### 3. Alinhamento de `PLANNING/06-api-react.md`

- **G:** nota de que `SharedProps.tenant` é `unknown` hoje (`types/src/inertia.ts:50`), com a
  decisão de tipar como `Tenant | null` num follow-up JS.

### 4. Roadmap `reports/roadmap-to-1.0.md`

- Marcar B-H como ✅ resolvidas na tabela §2.2 (doc alinhada / decisão registrada).
- Marcar §2.4 itens 1 (ADR-019), 3 (congelar convenções), 4 (ADR-007/014 Final) como feitos;
  item 2 (camada de factory) já estava resolvido via #342. Itens 5 (deprecation policy — agora
  no ADR) e 6 (sincronizar 07-roadmap-fases) anotados.

## Verificação

Como não há código de runtime, a verificação é documental:

1. **Consistência doc↔código:** para cada exemplo corrigido no `05-api-php.md`, um `grep` no
   código real confirma a convenção (ex.: `TextColumn::make` existe; `Column::text` não;
   `Actions::edit` existe). Feito durante a escrita.
2. **Formato do ADR:** o ADR-019 segue a estrutura dos ADRs 001-018 (Status/Contexto/Decisão/
   Consequências).
3. **Sem regressão:** nenhum arquivo `src/`/`tests/` tocado — só `PLANNING/*.md`, `reports/*.md`
   e o novo ADR. `git diff --stat` confirma só docs.

## Entrega

Um único PR `docs(gov): ADR-019 API freeze + align PLANNING API docs with code`. Sem testes
(não há código). Scope commitlint `gov` (ADRs/governança). Body referencia o roadmap §2.

## Fora de escopo (follow-ups)

- Tipar `SharedProps.tenant` como `Tenant | null` (G) — requer host com toolchain JS.
- Reescrever `PLANNING/07-roadmap-fases.md` (v1.0 ≠ fim-Fase-3) — doc de planejamento separada.
- Aviso runtime de `@deprecated` (a política fica no ADR; a infra de aviso é implementação futura).
