# Advanced fields

> Package: [`arqel-dev/fields-advanced`](../../packages/fields-advanced/) · Tickets: FIELDS-ADV-001..020

## Purpose

`arqel-dev/fields-advanced` groups the "rich" field types that don't belong to core `arqel-dev/fields`: WYSIWYG, Markdown, Code editors, dynamic structures (Repeater, Builder, KeyValue, Tags), and multi-step flows (Wizard).

The core × advanced split exists because these types pull in heavy JS dependencies (Tiptap, CodeMirror, Shiki) and more opinionated UI patterns — keeping `arqel-dev/fields` lean preserves boot time and bundle size in panels that only use simple inputs.

Each type registers itself on `Arqel\Fields\FieldFactory` in the provider's `packageBooted`, preserving the single-method ergonomics of `FieldFactory::richText('content')`.

## Quick start

```php
use Arqel\Fields\FieldFactory;

FieldFactory::richText('content')->toolbar(['bold', 'italic', 'link', 'code-block']);
FieldFactory::markdown('description')->preview()->previewMode('side-by-side');
FieldFactory::code('snippet')->language('php')->lineNumbers();
```

## RichText

`RichTextField` — `type='richText'`, component `RichTextInput` (Tiptap).

Setters: `toolbar(array)`, `imageUploadDisk(string)`, `imageUploadDirectory(string)`, `maxLength(int)` (clamp ≥1, default 65535), `fileAttachments(bool)`, `customMarks(array)`, `mentionable(array)` (entries without `id`+`name` are filtered).

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

Setters: `preview(bool)`, `previewMode(string)` with palette `'side-by-side'|'tab'|'popup'`, `toolbar(bool)`, `rows(int)` (clamp ≥3, default 10), `fullscreen(bool)`, `syncScroll(bool)`. The React preview chains `remark` + `rehype-sanitize`.

```php
FieldFactory::markdown('readme')->preview()->previewMode('tab')->rows(20);
```

## Code

`CodeField` — `type='code'`, component `CodeInput` (CodeMirror + Shiki).

Setters: `language(string)` (default `'plaintext'`), `theme(?string)` (null inherits from panel), `lineNumbers(bool)`, `wordWrap(bool)`, `tabSize(int)` (clamp ≥1, default 2), `minHeight(?int)`. React lazy-loads Shiki grammars.

```php
FieldFactory::code('config')->language('yaml')->lineNumbers()->tabSize(2);
```

## Repeater

`RepeaterField` — `type='repeater'`. List of sub-schemas.

Setters: `schema(array)` (filters non-`Field`), `minItems/maxItems(int)` (invariant `min ≤ max` throws `InvalidArgumentException`), `reorderable/collapsible/cloneable(bool)`, `itemLabel(string)` (template `"Address {{label}}"`), `relationship(string)` (Eloquent HasMany).

```php
FieldFactory::repeater('addresses')
    ->schema([
        FieldFactory::text('street')->required(),
        FieldFactory::text('city')->required(),
        FieldFactory::select('country')->options(['BR' => 'Brazil', 'PT' => 'Portugal']),
    ])
    ->minItems(1)
    ->maxItems(5)
    ->itemLabel('Address {{city}}')
    ->relationship('addresses');
```

## Builder

`BuilderField` + `Block` (abstract) — heterogeneous Repeater. Each block declares `static type/label/icon` + `schema()`.

```php
FieldFactory::builder('content')
    ->blocks([HeroBlock::class, GalleryBlock::class, QuoteBlock::class])
    ->minItems(1)
    ->reorderable()
    ->collapsible();
```

`blocks()` accepts `class-string<Block>[]` or a map `array<string, Block|class-string>`. **Duplicate `type()` throws `InvalidArgumentException`** — silent collisions would break payload routing.

## KeyValue

`KeyValueField` — `type='keyValue'`. Map editor.

Setters: `keyLabel/valueLabel(string)` (empty throws), `keyPlaceholder/valuePlaceholder(string)`, `editableKeys/addable/deletable/reorderable(bool)`, `asObject(bool)`.

`asObject(false)` (default) — emits an ordered list `[{key,value}]`. `asObject(true)` — emits a map `{key: value}` (last-wins on duplicates).

## Tags

`TagsField` — `type='tags'`.

Setters: `suggestions(array|Closure)` (Closure lazy, fallback `[]`), `creatable(bool)`, `maxTags(?int)` (clamp ≥1), `separator(string)` (empty throws), `uniqueTags(bool)` — renamed to avoid colliding with validation's `Field::unique()`; the payload key remains `unique`.

```php
FieldFactory::tags('keywords')
    ->suggestions(fn () => Tag::pluck('name')->all())
    ->creatable()
    ->maxTags(10);
```

## Wizard

`WizardField` + `Step` (value-object). Multi-step form.

`Step::make(name)->label(...)->icon(...)->schema([...])`. `WizardField::steps()` filters non-`Step`; **duplicate names throw `InvalidArgumentException`**.

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

**Where do I sanitize RichText HTML?**
The consumer's responsibility. Use `Purifier::clean($html)` in an Eloquent mutator or FormRequest — the package does NOT load `ezyang/htmlpurifier` as a hard dep. A sanitizer trait is planned.

**Can I nest a Repeater inside a Repeater?**
Technically yes, but avoid >2 levels — React performance degrades exponentially. Split into separate Resources or use Wizard.

**How do I persist a Repeater into a HasMany?**
Resource lifecycle hooks (`afterCreate`/`afterUpdate`) — cross-package delivery, outside this package's scope.

## Anti-patterns

- ❌ Persisting RichText HTML without purifying.
- ❌ Hard-deps on JS libs (Tiptap, CodeMirror, Shiki) in `composer.json`.
- ❌ Nesting Repeater/Builder beyond 2 levels.
- ❌ `toolbar([...])` with identifiers unknown to `RichTextInput.tsx` — silently ignored.

## Related

- [`packages/fields-advanced/SKILL.md`](../../packages/fields-advanced/SKILL.md)
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §FIELDS-ADV-001..020
- [Tiptap](https://tiptap.dev), [CodeMirror](https://codemirror.net), [Shiki](https://shiki.style)
