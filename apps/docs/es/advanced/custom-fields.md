# Fields avanzados

> Paquete: [`arqel-dev/fields-advanced`](../../packages/fields-advanced/) · Tickets: FIELDS-ADV-001..020

## Propósito

`arqel-dev/fields-advanced` agrupa los tipos de Field "ricos" que no pertenecen al core `arqel-dev/fields`: WYSIWYG, Markdown, editores de Code, estructuras dinámicas (Repeater, Builder, KeyValue, Tags) y flujos multipaso (Wizard).

La división core × advanced existe porque estos tipos arrastran dependencias JS pesadas (Tiptap, CodeMirror, Shiki) y patrones de UI más opinativos — mantener `arqel-dev/fields` ligero preserva el tiempo de boot y el tamaño del bundle en panels que solo usan inputs simples.

Cada tipo se registra en `Arqel\Fields\FieldFactory` en el `packageBooted` del Provider, preservando la ergonomía de método único de `FieldFactory::richText('content')`.

## Inicio rápido

```php
use Arqel\Fields\FieldFactory;

FieldFactory::richText('content')->toolbar(['bold', 'italic', 'link', 'code-block']);
FieldFactory::markdown('description')->preview()->previewMode('side-by-side');
FieldFactory::code('snippet')->language('php')->lineNumbers();
```

## RichText

`RichTextField` — `type='richText'`, componente `RichTextInput` (Tiptap).

Setters: `toolbar(array)`, `imageUploadDisk(string)`, `imageUploadDirectory(string)`, `maxLength(int)` (clamp ≥1, default 65535), `fileAttachments(bool)`, `customMarks(array)`, `mentionable(array)` (entradas sin `id`+`name` se filtran).

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

`MarkdownField` — `type='markdown'`, componente `MarkdownInput`.

Setters: `preview(bool)`, `previewMode(string)` con paleta `'side-by-side'|'tab'|'popup'`, `toolbar(bool)`, `rows(int)` (clamp ≥3, default 10), `fullscreen(bool)`, `syncScroll(bool)`. La preview React encadena `remark` + `rehype-sanitize`.

```php
FieldFactory::markdown('readme')->preview()->previewMode('tab')->rows(20);
```

## Code

`CodeField` — `type='code'`, componente `CodeInput` (CodeMirror + Shiki).

Setters: `language(string)` (default `'plaintext'`), `theme(?string)` (null hereda del panel), `lineNumbers(bool)`, `wordWrap(bool)`, `tabSize(int)` (clamp ≥1, default 2), `minHeight(?int)`. React carga lazy las gramáticas de Shiki.

```php
FieldFactory::code('config')->language('yaml')->lineNumbers()->tabSize(2);
```

## Repeater

`RepeaterField` — `type='repeater'`. Lista de sub-schemas.

Setters: `schema(array)` (filtra no-`Field`), `minItems/maxItems(int)` (invariante `min ≤ max` lanza `InvalidArgumentException`), `reorderable/collapsible/cloneable(bool)`, `itemLabel(string)` (template `"Address {{label}}"`), `relationship(string)` (Eloquent HasMany).

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

`BuilderField` + `Block` (abstracto) — Repeater heterogéneo. Cada bloque declara `static type/label/icon` + `schema()`.

```php
FieldFactory::builder('content')
    ->blocks([HeroBlock::class, GalleryBlock::class, QuoteBlock::class])
    ->minItems(1)
    ->reorderable()
    ->collapsible();
```

`blocks()` acepta `class-string<Block>[]` o un mapa `array<string, Block|class-string>`. **`type()` duplicado lanza `InvalidArgumentException`** — colisiones silenciosas romperían el routing del payload.

## KeyValue

`KeyValueField` — `type='keyValue'`. Editor de mapa.

Setters: `keyLabel/valueLabel(string)` (vacío lanza), `keyPlaceholder/valuePlaceholder(string)`, `editableKeys/addable/deletable/reorderable(bool)`, `asObject(bool)`.

`asObject(false)` (default) — emite una lista ordenada `[{key,value}]`. `asObject(true)` — emite un mapa `{key: value}` (last-wins en duplicados).

## Tags

`TagsField` — `type='tags'`.

Setters: `suggestions(array|Closure)` (Closure lazy, fallback `[]`), `creatable(bool)`, `maxTags(?int)` (clamp ≥1), `separator(string)` (vacío lanza), `uniqueTags(bool)` — renombrado para no chocar con `Field::unique()` de validación; la clave del payload sigue siendo `unique`.

```php
FieldFactory::tags('keywords')
    ->suggestions(fn () => Tag::pluck('name')->all())
    ->creatable()
    ->maxTags(10);
```

## Wizard

`WizardField` + `Step` (value-object). Formulario multipaso.

`Step::make(name)->label(...)->icon(...)->schema([...])`. `WizardField::steps()` filtra no-`Step`; **nombres duplicados lanzan `InvalidArgumentException`**.

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

**¿Dónde sanitizo el HTML del RichText?**
Es responsabilidad del consumidor. Usa `Purifier::clean($html)` en un mutator de Eloquent o FormRequest — el paquete NO carga `ezyang/htmlpurifier` como hard dep. Hay un trait sanitizador planificado.

**¿Puedo anidar un Repeater dentro de un Repeater?**
Técnicamente sí, pero evita >2 niveles — el rendimiento de React degrada exponencialmente. Divide en Resources separados o usa Wizard.

**¿Cómo persisto un Repeater en un HasMany?**
Hooks de ciclo de vida del Resource (`afterCreate`/`afterUpdate`) — entrega cross-package, fuera del alcance de este paquete.

## Anti-patrones

- ❌ Persistir HTML de RichText sin sanitizar.
- ❌ Hard-deps en libs JS (Tiptap, CodeMirror, Shiki) en `composer.json`.
- ❌ Anidar Repeater/Builder más allá de 2 niveles.
- ❌ `toolbar([...])` con identificadores desconocidos para `RichTextInput.tsx` — se ignoran silenciosamente.

## Relacionado

- [`packages/fields-advanced/SKILL.md`](../../packages/fields-advanced/SKILL.md)
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §FIELDS-ADV-001..020
- [Tiptap](https://tiptap.dev), [CodeMirror](https://codemirror.net), [Shiki](https://shiki.style)
