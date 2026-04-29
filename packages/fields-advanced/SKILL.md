# SKILL.md — arqel/fields-advanced

> Contexto canónico para AI agents.

## Purpose

`arqel/fields-advanced` agrupa os field types "ricos" que não fazem parte do core `arqel/fields`: editores WYSIWYG (RichText/Tiptap), Markdown, Code (Monaco), estruturas dinâmicas (Repeater, Builder, KeyValue, Tags) e fluxos multi-step (Wizard). Cada type registra-se no `Arqel\Fields\FieldFactory` no `packageBooted` do provider, mantendo a ergonomia única `FieldFactory::richText('content')`.

A separação core × advanced existe porque estes types arrastam dependências JS pesadas (Tiptap, Monaco) e padrões de UI mais opinionados — manter o `arqel/fields` enxuto preserva o tempo de boot e o bundle size em panels que só usam inputs simples.

## Status

**Entregue (FIELDS-ADV-001..002):**

- Esqueleto do pacote (`composer.json`, PSR-4 `Arqel\FieldsAdvanced\` → `src/`, deps em `arqel/core` + `arqel/fields` via path repos)
- `FieldsAdvancedServiceProvider` (final, auto-discovered) registra `richText` no `FieldFactory`
- **`Arqel\FieldsAdvanced\Types\RichTextField`** (final, extends `Arqel\Fields\Field`) — `type='richText'`, `component='RichTextInput'`. Setters fluentes `toolbar(array)` (drop silencioso de não-strings), `imageUploadDisk(string)`, `imageUploadDirectory(string)`, `maxLength(int)` (clamp ≥1, default 65535), `fileAttachments(bool=true)`, `customMarks(array)` (filtra não-strings), `mentionable(array)` (filtra entries sem `id`+`name` válidos; aceita `avatar` opcional). `getTypeSpecificProps()` devolve `{toolbar, imageUploadRoute, imageUploadDirectory, maxLength, fileAttachments, customMarks, mentionable}` — `imageUploadRoute` é `null` enquanto nenhum disk for configurado, caso contrário a string `/arqel/fields/upload?disk=<disk>` (sem `route()` para o campo permanecer testável fora de contexto HTTP)
- Pest 3 + Orchestra Testbench setup com `defineEnvironment` SQLite in-memory
- **13 testes Pest passando** — 3 ServiceProvider (boot, autoload, macro `richText` registrada) + 10 RichTextField (type/component, default toolbar, replace+drop não-strings, clamp `maxLength`, default `maxLength`, passthrough disco/diretório, `imageUploadRoute=null` sem disk, toggle `fileAttachments`, filtro `customMarks`, filtro `mentionable`, payload end-to-end)

**Por chegar (FIELDS-ADV-003..010):**

- `MarkdownField` — editor Markdown com preview (FIELDS-ADV-003)
- `CodeField` — Monaco editor com syntax highlight (FIELDS-ADV-004)
- `RepeaterField` — array dinâmico de sub-fields (FIELDS-ADV-005)
- `BuilderField` — repeater heterogêneo (blocks tipados) (FIELDS-ADV-006)
- `KeyValueField` — map editável (FIELDS-ADV-007)
- `TagsField` — input com chips e autocomplete (FIELDS-ADV-008)
- `WizardField` — fluxo multi-step com progresso (FIELDS-ADV-009)
- Sanitizer trait + integração com FormRequest para HTML purification em RichText/Markdown (FIELDS-ADV-002-followup)

## Conventions

- `declare(strict_types=1)` obrigatório
- Classes `final` por defeito
- **Sanitização HTML é responsabilidade do consumidor** — `RichTextField` só configura o editor; o pacote NÃO carrega `ezyang/htmlpurifier` ou similar como hard dep. Use FormRequest rules, mutators Eloquent ou o trait sanitizer planejado para FIELDS-ADV-002-followup
- `imageUploadRoute` é construído como string literal (`'/arqel/fields/upload?disk='.$disk`) porque a chamada `route()` exige roteamento ativo no container — manter literal preserva testabilidade unitária
- Setters silenciosamente filtram entradas inválidas (não-strings em `toolbar`/`customMarks`, entries sem `id`+`name` em `mentionable`) para que misconfigurações no PHP nunca cheguem ao React

## Anti-patterns

- ❌ **Persistir HTML do RichText sem purificar** — o editor produz HTML cliente-side; sempre rode `Purifier::clean($html)` (ou equivalente) num mutator/FormRequest antes de gravar
- ❌ **`toolbar([...])` com identifiers que o `RichTextInput.tsx` não conhece** — botões desconhecidos são silenciosamente ignorados pelo Tiptap; consulte a lista canónica suportada antes de customizar
- ❌ **Hard-dep em libs JS (Tiptap, Monaco) no `composer.json`** — dependências JS vivem em `@arqel/fields-advanced` (npm), nunca em `composer.json`

## Related

- Tickets: [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §FIELDS-ADV-001..010
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Field
- Source: [`packages/fields-advanced/src/`](./src/)
- Tests: [`packages/fields-advanced/tests/`](./tests/)
- Pacote irmão: [`packages/fields/`](../fields/) — base abstracta `Field` e `FieldFactory`
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
- Externos: [Tiptap](https://tiptap.dev) (RichText), [Monaco](https://microsoft.github.io/monaco-editor/) (Code)
