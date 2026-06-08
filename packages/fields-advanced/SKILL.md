# SKILL.md — arqel-dev/fields-advanced

> Contexto canónico para AI agents.

## Purpose

`arqel-dev/fields-advanced` agrupa os field types "ricos" que não fazem parte do core `arqel-dev/fields`: editores WYSIWYG (RichText/Tiptap), Markdown (CodeMirror + preview), Code (CodeMirror + Shiki), estruturas dinâmicas (Repeater, Builder, KeyValue, Tags) e fluxos multi-step (Wizard). Cada type registra-se no `Arqel\Fields\FieldFactory` no `packageBooted` do provider, mantendo a ergonomia única `FieldFactory::richText('content')`.

A separação core × advanced existe porque estes types arrastam dependências JS pesadas (Tiptap, CodeMirror, Shiki) e padrões de UI mais opinionados — manter o `arqel-dev/fields` enxuto preserva o tempo de boot e o bundle size em panels que só usam inputs simples.

## Status

**Setup (FIELDS-ADV-001):**

- Esqueleto do pacote (`composer.json`, PSR-4 `Arqel\FieldsAdvanced\` → `src/`, deps em `arqel-dev/core` + `arqel-dev/fields` via path repos)
- `FieldsAdvancedServiceProvider` (final, auto-discovered) registra 8 macros no `FieldFactory`: `richText`, `markdown`, `code`, `repeater`, `builder`, `keyValue`, `tags`, `wizard`
- Pest 3 + Orchestra Testbench setup com SQLite in-memory

**Rich content (FIELDS-ADV-002, 003, 004):**

- **`RichTextField`** — `type='richText'`, `component='RichTextInput'`. Setters: `toolbar(array)`, `imageUploadDisk(string)`, `imageUploadDirectory(string)`, `maxLength(int)` (clamp ≥1, default 65535), `fileAttachments(bool)`, `customMarks(array)`, `mentionable(array)` (filtra entries sem `id`+`name`). `imageUploadRoute` é construído como string literal `'/arqel-dev/fields/upload?disk='.$disk` (sem `route()` para preservar testabilidade).
- **`MarkdownField`** — `type='markdown'`, `component='MarkdownInput'`. Setters: `preview(bool)`, `previewMode(string)` com paleta `'side-by-side'|'tab'|'popup'` (constantes `PREVIEW_MODE_*`; valores desconhecidos fallback silencioso para `side-by-side`), `toolbar(bool)`, `rows(int)` (clamp ≥3, default 10), `fullscreen(bool)`, `syncScroll(bool)`. Preview React encadeia `remark` + `rehype-sanitize`.
- **`CodeField`** — `type='code'`, `component='CodeInput'`. Setters: `language(string)` (default `'plaintext'`), `theme(?string)` (null herda do panel), `lineNumbers(bool)`, `wordWrap(bool)`, `tabSize(int)` (clamp ≥1, default 2), `minHeight(?int)`. `readonly` herdado da `Field` base. React lazy-load grammars Shiki.

**Dynamic structure (FIELDS-ADV-005, 006):**

- **`RepeaterField`** — `type='repeater'`, `component='RepeaterInput'`. Setters: `schema(array)` (filtra não-`Field`), `minItems/maxItems(int)` (clamps + invariante `min ≤ max` lança `InvalidArgumentException`), `reorderable/collapsible/cloneable(bool)`, `itemLabel(string)` (template `"Address {{label}}"`), `relationship(string)` (HasMany Eloquent). Hidratação/persistência via lifecycle hooks do Resource fica fora do escopo (cross-package).
- **`Block`** (abstract) + **`BuilderField`** — `type='builder'`. `Block` declara `static type/label/icon` + `schema(): array<int, Field>`. `BuilderField::blocks()` aceita `class-string<Block>[]`, `array<string, Block|class-string>` ou misto; class-strings instanciados via `new $cls()`; **duplicate `type()` lança `InvalidArgumentException`**. Mesmas invariantes de `min/max` do Repeater.

**Map/list (FIELDS-ADV-007, 008):**

- **`KeyValueField`** — `type='keyValue'`. Setters: `keyLabel/valueLabel(string)` (vazio lança `InvalidArgumentException`), `keyPlaceholder/valuePlaceholder(string)`, `editableKeys/addable/deletable/reorderable(bool)`, `asObject(bool)` — switch de output: `false` (default) emite lista ordenada `[{key,value}]`, `true` emite map `{key: value}` (last-wins).
- **`TagsField`** — `type='tags'`. Setters: `suggestions(array|Closure)` (Closure resolvida lazy, fallback `[]`), `creatable(bool)`, `maxTags(?int)` (clamp ≥1), `separator(string)` (vazio lança `InvalidArgumentException`), `uniqueTags(bool)` (renomeado para evitar colisão com `Field::unique()` de validação; chave do payload continua `unique`). Integração Spatie/laravel-tags fica fora do escopo.

**Multi-step (FIELDS-ADV-009):**

- **`Step`** (value-object, final) — `name`, `label` (fallback humanised via `Str::headline`), `icon(?string)`, `schema(array)`. `toArray()` emite `{name, label, icon, schema}`.
- **`WizardField`** — `type='wizard'`. Setters: `steps(array)` (filtra não-`Step`; **duplicate names lança `InvalidArgumentException`**), `persistInUrl(bool)`, `skippable(bool)`. Form/FormRenderer integration (tratar Wizard como layout) fica fora do escopo (depende de FORM-005).

**Serialização de schemas aninhados (#221):** os sub-fields de `RepeaterField::schema()`, `Block::schema()` e `Step::schema()` são serializados pela **FieldSchema canônica completa** via `Arqel\Core\Support\FieldSchemaSerializer::serialize()` — cada child ship a mesma forma rica do form de topo (`{name, type, label, placeholder, props, validation, ...}`, com options de `SelectField` aninhadas sob `props.options`). **Nunca** colapse um child para `{name, type}` (era o bug #221: o guard `method_exists($child,'toArray')` era sempre falso pois nenhum `Field` define `toArray()` → dropdown aninhado vazio). Os inputs React leem options via `field.props?.options ?? field.options` (canônico `props.options`, fallback flat).

**Coverage:** 133 testes Pest passando — 10 ServiceProvider + 11 RichText + 11 Markdown + 12 Code + 16 Repeater + 6 Block + 15 Builder + 14 KeyValue + 14 Tags + 6 Step + 12 Wizard + 6 CrossField.

**Por chegar:**

- React components `RichTextInput`/`MarkdownInput`/`CodeInput`/`RepeaterInput`/`BuilderInput`/`KeyValueInput`/`TagsInput`/`WizardInput` (FIELDS-ADV-010..017)
- Registry boot client-side (FIELDS-ADV-018)
- Spatie/laravel-tags integration em `TagsField::fromRelationship(...)`
- Sanitizer trait + FormRequest helper para HTML purification em RichText/Markdown
- Repeater hidratação/persistência (HasMany binding via Resource lifecycle hooks `afterCreate`/`afterUpdate`)

## Conventions

- `declare(strict_types=1)` obrigatório; classes `final` por defeito (exceto `Block` abstract).
- **PHP é config-only** — sanitização HTML/Markdown/Code é responsabilidade do consumidor. O pacote NÃO carrega `ezyang/htmlpurifier` nem qualquer parser Markdown como hard dep. Use FormRequest rules, mutators Eloquent ou o sanitizer trait planejado.
- Setters silenciosamente filtram entradas inválidas (não-strings em arrays de config, `Field`/`Block`/`Step` checks) para que misconfiguração no PHP nunca chegue ao React.
- Valores enum-like (`previewMode`) degradam para default em vez de lançar — typo não deve crashar Inertia render.
- Invariantes estruturais (`min ≤ max`, duplicate `Block::type()`/`Step` name) lançam `InvalidArgumentException` — colisões silenciosas quebrariam roteamento/payload.

## Anti-patterns

- ❌ **Persistir HTML do RichText sem purificar** — sempre rode `Purifier::clean($html)` (ou equivalente) num mutator/FormRequest antes de gravar.
- ❌ **Hard-dep em libs JS (Tiptap, CodeMirror, Shiki) no `composer.json`** — dependências JS vivem em `@arqel-dev/fields-advanced` (npm), nunca em `composer.json`.
- ❌ **Aninhar `Repeater`/`Builder` além de 2 níveis** — performance React degrada exponencialmente com FormRenderer recursivo; quebre em Resources separados ou use Wizard.
- ❌ **`toolbar([...])` com identifiers que `RichTextInput.tsx` não conhece** — botões desconhecidos são ignorados pelo Tiptap; consulte a lista canónica.

## Examples

RichText com toolbar customizado e upload de imagens:

```php
use Arqel\Fields\FieldFactory;

FieldFactory::richText('content')
    ->toolbar(['bold', 'italic', 'link', 'image', 'code-block'])
    ->imageUploadDisk('public')
    ->imageUploadDirectory('posts/images')
    ->maxLength(20000)
    ->mentionable([
        ['id' => 1, 'name' => 'Alice', 'avatar' => '/a.png'],
    ]);
```

Repeater com sub-schema:

```php
use Arqel\Fields\FieldFactory;

FieldFactory::repeater('addresses')
    ->schema([
        FieldFactory::text('street')->required(),
        FieldFactory::text('city')->required(),
        FieldFactory::select('country')->options(['BR' => 'Brasil', 'PT' => 'Portugal']),
    ])
    ->minItems(1)
    ->maxItems(5)
    ->itemLabel('Address {{city}}')
    ->relationship('addresses');
```

Builder com blocks heterogêneos:

```php
use Arqel\Fields\FieldFactory;
use App\Blocks\{HeroBlock, GalleryBlock, QuoteBlock};

FieldFactory::builder('content')
    ->blocks([HeroBlock::class, GalleryBlock::class, QuoteBlock::class])
    ->minItems(1)
    ->reorderable()
    ->collapsible();
```

Wizard com steps:

```php
use Arqel\Fields\FieldFactory;
use Arqel\FieldsAdvanced\Steps\Step;

FieldFactory::wizard('onboarding')
    ->steps([
        Step::make('account')->icon('user')->schema([
            FieldFactory::text('name')->required(),
            FieldFactory::text('email')->required()->email(),
        ]),
        Step::make('profile')->icon('id-card')->schema([
            FieldFactory::textarea('bio'),
        ]),
        Step::make('confirm')->schema([
            FieldFactory::checkbox('terms')->required(),
        ]),
    ])
    ->persistInUrl()
    ->skippable();
```

## Related

- Tickets: [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §FIELDS-ADV-001..020
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Field
- Source: [`packages/fields-advanced/src/`](./src/)
- Tests: [`packages/fields-advanced/tests/`](./tests/)
- Pacote irmão: [`packages/fields/`](../fields/) — base abstracta `Field` e `FieldFactory`
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
- Externos: [Tiptap](https://tiptap.dev) (RichText), [CodeMirror](https://codemirror.net) + [Shiki](https://shiki.style) (Code/Markdown)
