# Advanced fields

> Pacote: [`arqel/fields-advanced`](../../packages/fields-advanced/) · Tickets: FIELDS-ADV-001..020

## Purpose

`arqel/fields-advanced` agrupa os field types "ricos" que não fazem parte do core `arqel/fields`: editores WYSIWYG, Markdown, Code, estruturas dinâmicas (Repeater, Builder, KeyValue, Tags) e fluxos multi-step (Wizard).

A separação core × advanced existe porque estes types arrastam dependências JS pesadas (Tiptap, CodeMirror, Shiki) e padrões de UI mais opinionados — manter o `arqel/fields` enxuto preserva tempo de boot e bundle size em panels que só usam inputs simples.

Cada type registra-se no `Arqel\Fields\FieldFactory` no `packageBooted` do provider, mantendo a ergonomia única `FieldFactory::richText('content')`.

## Quick start

```php
use Arqel\Fields\FieldFactory;

FieldFactory::richText('content')->toolbar(['bold', 'italic', 'link', 'code-block']);
FieldFactory::markdown('description')->preview()->previewMode('side-by-side');
FieldFactory::code('snippet')->language('php')->lineNumbers();
```

## RichText

`RichTextField` — `type='richText'`, component `RichTextInput` (Tiptap).

Setters: `toolbar(array)`, `imageUploadDisk(string)`, `imageUploadDirectory(string)`, `maxLength(int)` (clamp ≥1, default 65535), `fileAttachments(bool)`, `customMarks(array)`, `mentionable(array)` (entries sem `id`+`name` filtradas).

```php
FieldFactory::richText('content')
    ->toolbar(['bold', 'italic', 'link', 'image', 'code-block'])
    ->imageUploadDisk('public')
    ->imageUploadDirectory('posts/images')
    ->maxLength(20000)
    ->mentionable([
        ['id' => 1, 'name' => 'Alice', 'avatar' => '/a.png'],
    ]);
```

## Markdown

`MarkdownField` — `type='markdown'`, component `MarkdownInput`.

Setters: `preview(bool)`, `previewMode(string)` com paleta `'side-by-side'|'tab'|'popup'`, `toolbar(bool)`, `rows(int)` (clamp ≥3, default 10), `fullscreen(bool)`, `syncScroll(bool)`. Preview React encadeia `remark` + `rehype-sanitize`.

```php
FieldFactory::markdown('readme')->preview()->previewMode('tab')->rows(20);
```

## Code

`CodeField` — `type='code'`, component `CodeInput` (CodeMirror + Shiki).

Setters: `language(string)` (default `'plaintext'`), `theme(?string)` (null herda do panel), `lineNumbers(bool)`, `wordWrap(bool)`, `tabSize(int)` (clamp ≥1, default 2), `minHeight(?int)`. React lazy-load grammars Shiki.

```php
FieldFactory::code('config')->language('yaml')->lineNumbers()->tabSize(2);
```

## Repeater

`RepeaterField` — `type='repeater'`. Lista de sub-schemas.

Setters: `schema(array)` (filtra não-`Field`), `minItems/maxItems(int)` (invariante `min ≤ max` lança `InvalidArgumentException`), `reorderable/collapsible/cloneable(bool)`, `itemLabel(string)` (template `"Address {{label}}"`), `relationship(string)` (HasMany Eloquent).

```php
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

## Builder

`BuilderField` + `Block` (abstract) — Repeater heterogêneo. Cada block declara `static type/label/icon` + `schema()`.

```php
FieldFactory::builder('content')
    ->blocks([HeroBlock::class, GalleryBlock::class, QuoteBlock::class])
    ->minItems(1)
    ->reorderable()
    ->collapsible();
```

`blocks()` aceita `class-string<Block>[]` ou map `array<string, Block|class-string>`. **Duplicate `type()` lança `InvalidArgumentException`** — colisões silenciosas quebrariam roteamento de payload.

## KeyValue

`KeyValueField` — `type='keyValue'`. Map editor.

Setters: `keyLabel/valueLabel(string)` (vazio lança), `keyPlaceholder/valuePlaceholder(string)`, `editableKeys/addable/deletable/reorderable(bool)`, `asObject(bool)`.

`asObject(false)` (default) — emite lista ordenada `[{key,value}]`. `asObject(true)` — emite map `{key: value}` (last-wins em duplicatas).

## Tags

`TagsField` — `type='tags'`.

Setters: `suggestions(array|Closure)` (Closure lazy, fallback `[]`), `creatable(bool)`, `maxTags(?int)` (clamp ≥1), `separator(string)` (vazio lança), `uniqueTags(bool)` — renomeado para evitar colisão com `Field::unique()` de validação; chave do payload continua `unique`.

```php
FieldFactory::tags('keywords')
    ->suggestions(fn () => Tag::pluck('name')->all())
    ->creatable()
    ->maxTags(10);
```

## Wizard

`WizardField` + `Step` (value-object). Multi-step form.

`Step::make(name)->label(...)->icon(...)->schema([...])`. `WizardField::steps()` filtra não-`Step`; **duplicate names lança `InvalidArgumentException`**.

```php
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

## FAQ

**Onde sanitizo HTML do RichText?**
Responsabilidade do consumidor. Use `Purifier::clean($html)` num mutator Eloquent ou FormRequest — o pacote NÃO carrega `ezyang/htmlpurifier` como hard dep. Sanitizer trait planejado.

**Posso aninhar Repeater dentro de Repeater?**
Tecnicamente sim, mas evite >2 níveis — performance React degrada exponencialmente. Quebre em Resources separados ou use Wizard.

**Como persisto Repeater num HasMany?**
Lifecycle hooks do Resource (`afterCreate`/`afterUpdate`) — entrega cross-package, fora do escopo deste pacote.

## Anti-patterns

- ❌ Persistir HTML do RichText sem purificar.
- ❌ Hard-dep em libs JS (Tiptap, CodeMirror, Shiki) no `composer.json`.
- ❌ Aninhar Repeater/Builder além de 2 níveis.
- ❌ `toolbar([...])` com identifiers desconhecidos pelo `RichTextInput.tsx` — silenciosamente ignorados.

## Related

- [`packages/fields-advanced/SKILL.md`](../../packages/fields-advanced/SKILL.md)
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §FIELDS-ADV-001..020
- [Tiptap](https://tiptap.dev), [CodeMirror](https://codemirror.net), [Shiki](https://shiki.style)
