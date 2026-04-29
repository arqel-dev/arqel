# SKILL.md — arqel/fields-advanced

> Contexto canónico para AI agents.

## Purpose

`arqel/fields-advanced` agrupa os field types "ricos" que não fazem parte do core `arqel/fields`: editores WYSIWYG (RichText/Tiptap), Markdown, Code (Monaco), estruturas dinâmicas (Repeater, Builder, KeyValue, Tags) e fluxos multi-step (Wizard). Cada type registra-se no `Arqel\Fields\FieldFactory` no `packageBooted` do provider, mantendo a ergonomia única `FieldFactory::richText('content')`.

A separação core × advanced existe porque estes types arrastam dependências JS pesadas (Tiptap, Monaco) e padrões de UI mais opinionados — manter o `arqel/fields` enxuto preserva o tempo de boot e o bundle size em panels que só usam inputs simples.

## Status

**Entregue (FIELDS-ADV-001..005):**

- Esqueleto do pacote (`composer.json`, PSR-4 `Arqel\FieldsAdvanced\` → `src/`, deps em `arqel/core` + `arqel/fields` via path repos)
- `FieldsAdvancedServiceProvider` (final, auto-discovered) registra `richText`, `markdown`, `code` e `repeater` no `FieldFactory`
- **`Arqel\FieldsAdvanced\Types\RichTextField`** (final, extends `Arqel\Fields\Field`) — `type='richText'`, `component='RichTextInput'`. Setters fluentes `toolbar(array)` (drop silencioso de não-strings), `imageUploadDisk(string)`, `imageUploadDirectory(string)`, `maxLength(int)` (clamp ≥1, default 65535), `fileAttachments(bool=true)`, `customMarks(array)` (filtra não-strings), `mentionable(array)` (filtra entries sem `id`+`name` válidos; aceita `avatar` opcional). `getTypeSpecificProps()` devolve `{toolbar, imageUploadRoute, imageUploadDirectory, maxLength, fileAttachments, customMarks, mentionable}` — `imageUploadRoute` é `null` enquanto nenhum disk for configurado, caso contrário a string `/arqel/fields/upload?disk=<disk>` (sem `route()` para o campo permanecer testável fora de contexto HTTP)
- **`Arqel\FieldsAdvanced\Types\MarkdownField`** (final, extends `Arqel\Fields\Field`) — `type='markdown'`, `component='MarkdownInput'`. Setters fluentes `preview(bool=true)`, `previewMode(string)` com paleta `'side-by-side' | 'tab' | 'popup'` (constantes `PREVIEW_MODE_SIDE_BY_SIDE/TAB/POPUP`; valores desconhecidos fazem fallback silencioso para `'side-by-side'`), `toolbar(bool=true)`, `rows(int)` (clamp ≥3, default 10), `fullscreen(bool=true)` e o knob extra `syncScroll(bool=true)` (sincroniza scroll entre editor e preview). `getTypeSpecificProps()` devolve `{preview, previewMode, toolbar, rows, fullscreen, syncScroll}`. Defaults preservam UX out-of-the-box: preview lado-a-lado, toolbar visível, fullscreen disponível, scroll sincronizado. Sanitização Markdown→HTML é responsabilidade do consumidor (PHP é config-only); a preview React usa `remark` + `rehype-sanitize` como camada client-side
- **`Arqel\FieldsAdvanced\Types\CodeField`** (final, extends `Arqel\Fields\Field`) — `type='code'`, `component='CodeInput'`. Setters fluentes `language(string)` (default `'plaintext'`), `theme(?string)` (null deixa o React herdar do toggle dark/light do panel), `lineNumbers(bool=true)` (default `true`), `wordWrap(bool=true)` (default `false`), `tabSize(int)` (clamp ≥1, default 2), `minHeight(?int)` (clamp ≥0 quando inteiro, `null` reseta para o default React-side). O flag `readonly` é herdado da `Field` base (`readonly(bool=true)`) e re-emitido no payload. `getTypeSpecificProps()` devolve `{language, theme, lineNumbers, wordWrap, tabSize, readonly, minHeight}`. PHP é config-only — a render React usa CodeMirror 6 + Shiki com lazy-load das grammars; sanitização do código submetido (XSS no render, command injection se for `eval`'d) é responsabilidade do consumidor
- **`Arqel\FieldsAdvanced\Types\RepeaterField`** (final, extends `Arqel\Fields\Field`) — `type='repeater'`, `component='RepeaterInput'`. Construção via `new RepeaterField($name)`, `RepeaterField::make($name)` ou macro `FieldFactory::repeater($name)`. Setters fluentes `schema(array)` (filtra silenciosamente entradas que não são `Field`), `minItems(int)` (clamp ≥0), `maxItems(int)` (clamp ≥1; lança `InvalidArgumentException` se o novo max for menor que o `minItems` já configurado, preservando o invariante `min ≤ max`), `reorderable(bool=true)`, `collapsible(bool=true)`, `cloneable(bool=true)`, `itemLabel(string)` (template tipo `"Address {{label}}"`), `relationship(string)` (nome da HasMany Eloquent). `getTypeSpecificProps()` devolve `{schema, minItems, maxItems, reorderable, collapsible, cloneable, itemLabel, relationship}` — cada child é serializado via `toArray()` quando disponível, caso contrário cai num payload mínimo `{name, type}`. **Hidratação a partir do registro e persistência (`afterCreate`/`afterUpdate` chamando `$record->{relationship}()->create($item)`) ficam fora do escopo deste ticket**: precisam dos hooks de lifecycle do Resource em `arqel/core` e são tratadas como trabalho cross-package em ticket subsequente. O lado React (`RepeaterInput.tsx` com dnd-kit + FormRenderer aninhado) é FIELDS-JS-XXX
- Pest 3 + Orchestra Testbench setup com `defineEnvironment` SQLite in-memory
- **54 testes Pest passando** — 6 ServiceProvider (boot, autoload, macros `richText`, `markdown`, `code` e `repeater` registradas) + 10 RichTextField + 11 MarkdownField + 12 CodeField + 15 RepeaterField (type/component, construção via construtor + `make` + macro `FieldFactory::repeater`, defaults canónicos, filtragem silenciosa de não-`Field` em `schema()`, clamp `minItems` ≥0, clamp `maxItems` ≥1, throw `InvalidArgumentException` quando max < min, toggles `reorderable`/`collapsible`/`cloneable`, persistência de `itemLabel`/`relationship`, payload completo serializado end-to-end)

**Por chegar (FIELDS-ADV-006..010):**

- `BuilderField` — repeater heterogêneo (blocks tipados) (FIELDS-ADV-006)
- `KeyValueField` — map editável (FIELDS-ADV-007)
- `TagsField` — input com chips e autocomplete (FIELDS-ADV-008)
- `WizardField` — fluxo multi-step com progresso (FIELDS-ADV-009)
- Sanitizer trait + integração com FormRequest para HTML purification em RichText/Markdown (FIELDS-ADV-002-followup)

## Conventions

- `declare(strict_types=1)` obrigatório
- Classes `final` por defeito
- **Sanitização HTML/Markdown é responsabilidade do consumidor** — `RichTextField` e `MarkdownField` só configuram o editor; o pacote NÃO carrega `ezyang/htmlpurifier` nem qualquer parser Markdown como hard dep. Use FormRequest rules, mutators Eloquent ou o trait sanitizer planejado para FIELDS-ADV-002-followup. Para Markdown, a preview React encadeia `remark` + `rehype-sanitize`, mas qualquer renderização server-side (blade, e-mail, RSS) deve sanitizar no boundary
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
