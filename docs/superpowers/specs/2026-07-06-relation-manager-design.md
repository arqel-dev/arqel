# Relation Manager (milestone 0.18) — Design Spec

> **Status:** aprovado (brainstorming 2026-07-06). Fonte para o plano de implementação.
> **Milestone:** 0.18 — fecha a lacuna competitiva #2 (Relation Managers) vs Filament/Nova.
> **Base:** v0.15.1; após 0.17 Imports (PR #346) mergeado.

## Objetivo

Entregar **Relation Managers** no estilo Filament: uma aba na página de edição de um Resource que lista os registros de uma relação Eloquent do registro-pai numa Table reutilizada, com create/edit/delete próprios (HasMany/MorphMany) e attach/detach via pivot (BelongsToMany) — cada operação autorizada pela Policy do model relacionado.

**Escopo decidido (brainstorming):**
1. Relation Manager Filament-style (os novos relation-*fields* ficam para 0.18b).
2. MVP de mutações: **CRUD de HasMany/MorphMany + attach/detach de BelongsToMany**.
3. create/edit em **modal na mesma página** (contexto do pai preservado).
4. Declaração via **classe dedicada** `RelationManager` registrada em `Resource::relations(): array`.

## Contexto factual (exploração do código)

Reutilizável como está:
- `Arqel\Table\Table` (schema declarativo, sem acoplamento a Resource) — lista os relacionados.
- `Arqel\Form\Form` (schema declarativo) — create/edit no modal.
- `TableQueryBuilder` — engine de paginação/filtro/sort (mesma da index de Resource).
- React: `DataTable`/`TableToolbar`/`TableFilters`/`TablePagination`, `FormRenderer`, `Modal`, e o picker de `BelongsToInput`.
- Autorização: `ResourceController::authorize()` (Gate + fail-open sem policy), `PolicyDiscovery`, trait `HasAuthorization` (Actions e Fields).
- Precedente de despacho: `ExportAction`/`ImportAction` roteados por um controller genérico com lookup duck-typed — **não** um controller por feature.

A construir (8 gaps): `Resource::relations()`, a classe `RelationManager`, o controller relation-scoped, o wrapper React de abas page-level, os modais, e a correção da divergência de nome do `HasManyReadonly.tsx`.

---

## Seção 1 — Arquitetura & API PHP

**Novo contrato** (`packages/core`):

```php
abstract class RelationManager
{
    public static string $relationship;         // nome da relação Eloquent no model do pai (ex: 'comments', 'tags')

    abstract public function table(): Table;     // reusa Arqel\Table\Table — lista os relacionados
    public function form(): ?Form;               // reusa Arqel\Form\Form — create/edit no modal (null = sem create/edit)
    public function relatedResource(): ?string;  // opcional: Resource do relacionado (herda policy/título)
    public function authorize(): array;          // predicados bespoke opcionais (via HasAuthorization)

    public function toArray(?Model $parent = null): array; // serialização p/ props Inertia
}
```

Ponto de extensão no Resource (aditivo, não-breaking — segue o padrão duck-typed que o `ResourceController` já usa para `table()`/`form()`):

```php
// Resource.php
public function relations(): array { return []; }   // default vazio → zero regressão
public function getRelations(): array { /* resolve + valida instâncias */ }
```

**Detecção de tipo de relação em runtime:** o manager inspeciona `$parent->{$relationship}()` (a instância da relação Eloquent) para decidir a semântica — `HasMany`/`MorphMany` → CRUD; `BelongsToMany` → attach/detach + create com pivot. Não é declarado pelo dev — menos superfície.

**Slug:** cada RelationManager tem um slug estável (derivado de `$relationship`) usado nas rotas e como allowlist.

---

## Seção 2 — Roteamento & Controller PHP

Um **controller genérico relation-scoped** (`RelationController`), não um por relação (segue o precedente Export/Import).

Rotas (registradas junto às do Resource, sob o mesmo middleware do painel — `web + auth` + guard do painel):

```
GET    {resource}/{parent}/relations/{relation}                       → index    (lista paginada/filtrada)
GET    {resource}/{parent}/relations/{relation}/create                → create   (schema do form p/ modal)
POST   {resource}/{parent}/relations/{relation}                       → store    (cria filho; injeta FK/morph)
GET    {resource}/{parent}/relations/{relation}/{related}/edit        → edit     (schema + dados p/ modal)
PUT    {resource}/{parent}/relations/{relation}/{related}             → update
DELETE {resource}/{parent}/relations/{relation}/{related}             → destroy
POST   {resource}/{parent}/relations/{relation}/attach                → attach   (BelongsToMany: associa existente via pivot)
DELETE {resource}/{parent}/relations/{relation}/{related}/detach      → detach   (BelongsToMany: desassocia, não deleta)
```

- `{relation}` é validado contra a allowlist de `Resource::relations()` → **404** se não estiver (não aceita nome arbitrário — mesma lição de segurança do import I1, evita instanciação/acesso arbitrário).
- `{related}` é **sempre escopado por `{parent}`** na query (`$parent->{relation}()->findOrFail($related)`) — um related de outro pai → **404** (previne IDOR).

**Semântica por tipo (detectada em runtime):**
- **HasMany/MorphMany:** `store` = `$parent->{relation}()->create($data)` (FK/morph injetados pelo Eloquent). `attach`/`detach` → **405** (não aplicável).
- **BelongsToMany:** `store` cria o registro **e** faz attach. `attach` = `$parent->{relation}()->attach($id, $pivotData)`. `detach` = `$parent->{relation}()->detach($id)` (não deleta o registro).

**Serialização:** `RelationManager::toArray($parent)` → `{ slug, label, type, table_schema, form_schema, abilities }`, onde `abilities` é o mapa `{ create, update, delete, attach, detach }` de booleanos **já computados server-side** para o usuário atual (a Policy do model relacionado é consultada uma vez na serialização; o React só lê o booleano, nunca re-decide autorização). Vai nas props Inertia da página de edição do pai. Teste assertará o payload **de ponta a ponta** (PHP `toArray()` → props → consumo React) — mitiga o gap sistêmico "documented-but-unwired serialization" dos loops anteriores.

---

## Seção 3 — React (aba na página de edição + modal)

Componentes novos (`packages-js/ui`), todos via shadcn primitives + cva + tokens OKLCH + Tailwind responsivo (zero CSS ad-hoc):

1. **`ResourceEditTabs`** — wrapper page-level em torno do `ArqelEditPage`. Sem `props.relations` → renderiza o form como hoje (zero regressão). Com relations → abas **"Dados"** (o form) + uma por RelationManager. Sobre o primitivo shadcn `Tabs` (Radix, acessível), **não** o `FormTabs` (que é field-level). Aba ativa persistida na URL (`?tab=slug`) p/ deep-link/refresh. Em telas estreitas: select/scroll horizontal (padrão shadcn).

2. **`RelationManagerPanel`** — conteúdo de uma aba: um `DataTable` alimentado pela rota `index` (paginação/filtro/sort server-side via engine existente); toolbar com **"Novo"** (ability `create`) e — só BelongsToMany — **"Anexar"** (attach); por linha **Editar** (`update`), **Excluir/Desanexar** (delete/detach conforme o tipo).

3. **`RelationFormModal`** — reusa `Modal` + `FormRenderer`. Abre com schema de `create`/`edit`; submete via Inertia p/ `store`/`update`; on-success fecha e recarrega **só** as props da relação (Inertia partial reload `only: ['relations']`), sem recarregar a página nem perder o estado do form do pai. Erros de validação vêm pelos `errors` do Inertia; loading via `processing` do `useForm`.

4. **`AttachModal`** (só BelongsToMany) — picker (reusa o padrão do `BelongsToInput`) p/ escolher um registro existente + campos de pivot opcionais; submete p/ `attach`.

**Data flow:** Inertia-only (ADR-001), sem fetch lib nova (ADR-016). Modais usam `router.post/put` com `preserveScroll` + `only:` p/ atualização cirúrgica.

**Fix incluído (dívida §4/§7 da exploração):** corrigir a divergência de nome do `HasManyReadonly.tsx` (o PHP declara componente `HasManyTable` mas registra `HasManyReadonly`, um `<ul>` cru). **Não** reescrever o HasManyField inline (fora de escopo — Repeater/0.18b). Quando existe RelationManager para a mesma relação, ele é a fonte rica; o field inline segue read-only p/ casos simples.

---

## Seção 4 — Testes, autorização & escopo

**Testes** (ADR-008; ≥90% PHP / ≥80% JS):

*PHP (Pest, pacote `core`):*
- Unit `RelationManager`: detecção de tipo, `toArray()` serializa slug/label/schemas/abilities, allowlist.
- Feature `RelationController` (fixtures: `Post` hasMany `Comment`; `Post` belongsToMany `Tag`):
  - index lista só os relacionados do `{parent}` (related de outro pai → 404, anti-IDOR);
  - store cria filho com FK/morph; validação por form schema;
  - update/destroy escopados ao pai;
  - attach associa via pivot; detach desassocia **sem deletar** (assert Tag ainda existe);
  - attach/detach em HasMany → 405;
  - `{relation}` fora da allowlist → 404;
  - cada verbo negado pela Policy → 403; fail-open sem policy.

*JS (Vitest, pacote `ui`):*
- `ResourceEditTabs`: sem relations → só form (zero-regressão); com relations → abas + `?tab=`.
- `RelationManagerPanel`: botões condicionais por ability; ações por linha por tipo.
- `RelationFormModal`: submit → partial reload `only:['relations']`; erros inline.

*E2E (Playwright, dogfood porta 8090):* 1 spec — abrir edição → aba de relação → criar filho no modal → editar → excluir; attach/detach de um BelongsToMany. Adiciona um RelationManager ao showcase p/ dogfood real.

**Autorização — matriz** (Gate contra a Policy do **model relacionado**, fail-open sem policy):

| Endpoint | Ability | Alvo |
|---|---|---|
| index | `viewAny` | related model |
| create / store | `create` | related model |
| edit / update | `update` | related record (escopado ao pai) |
| destroy | `delete` | related record |
| attach | `attach` (fallback `create`) | related + pivot |
| detach | `detach` (fallback `delete`) | vínculo (não o registro) |

Camada extra: `RelationManager::authorize()` (trait `HasAuthorization`) p/ predicados bespoke. Campos submetidos passam pelo `pruneUnauthorizedFields` existente. Abilities próprias `attach`/`detach` resolvem o gap (as abilities CRUD não mapeiam attach/detach 1:1), com fallback p/ `create`/`delete`.

**Fora de escopo (YAGNI → 0.18b ou depois):**
- Novos relation-*fields* (BelongsToMany/MorphTo/MorphMany/HasManyThrough como campos) → 0.18b.
- `MorphTo` e `HasManyThrough` como RelationManagers → MVP só HasMany/MorphMany/BelongsToMany.
- Reescrita do HasManyField inline / Repeater → Phase 2.
- Edição inline na célula; reordenação de relacionados; nested relation managers.

**Contrato de entrega:** pacotes afetados = `core` (Resource + RelationController + RelationManager) e `ui` (React). **Não** é pacote novo → sem os 4 pontos de registro de pacote. Serialização testada de ponta-a-ponta.

---

## Unidades e interfaces (isolamento)

| Unidade | Faz | Depende de |
|---|---|---|
| `RelationManager` (abstract) | Declara relação + table + form + authz; serializa | Table, Form, HasAuthorization |
| `Resource::relations()` | Lista os managers de um Resource | RelationManager |
| `RelationController` | Despacha os 8 endpoints; escopa por pai; autoriza | RelationManager, Gate, TableQueryBuilder, Form |
| `ResourceEditTabs` (React) | Abas page-level: form + painéis de relação | shadcn Tabs, ArqelEditPage |
| `RelationManagerPanel` (React) | DataTable + toolbar/ações de uma relação | DataTable, RelationFormModal, AttachModal |
| `RelationFormModal` / `AttachModal` | Create/edit / attach via Inertia | Modal, FormRenderer, BelongsToInput |

Cada unidade tem propósito único e interface bem definida; testável isoladamente.
