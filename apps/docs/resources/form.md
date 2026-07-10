# Form

`Arqel\Form\Form` is the fluent builder behind a Resource's create/edit pages. It arranges Fields into sections, grids, tabs, and other layout components, then serializes to a schema that `@arqel-dev/ui`'s `<FormRenderer>` renders on the client.

Like `table()`, `form()` on a Resource is optional. When you don't declare it, Arqel renders `Resource::fields()` in a straightforward single-column layout. Reach for a custom `Form` when you need multi-column layouts, sections, tabs, or conditional visibility at the layout level.

Full method reference: [`arqel-dev/form`](/reference/php/form).

## The minimum

```php
use Arqel\Form\Form;
use Arqel\Form\Layout\{Section, Grid, Tabs, Tab};
use Arqel\Fields\FieldFactory as Field;

public function form(): Form
{
    return Form::make()->schema([
        Section::make('Content')
            ->description('Post title and body')
            ->schema([
                Field::text('title')->required()->columnSpan(2),
                Field::slug('slug')->fromField('title'),
            ])
            ->columns(2),

        Section::make('Publishing')
            ->aside()   // renders as a side panel on desktop
            ->schema([
                Field::select('status')->options(['draft' => 'Draft', 'published' => 'Published']),
                Field::dateTime('published_at'),
            ]),
    ])->columns(3);
}
```

`schema()` accepts a heterogeneous mix of Fields and layout components — nest Fields inside layout components freely, but not the other way around.

## Layout components

`Arqel\Form\Layout\Component` is the shared abstract base — every layout component supports `$columnSpan`, `$visibleIf`, and `$canSee`.

| Class | Extra setters | Use case |
|---|---|---|
| `Section` | `heading`, `description`, `icon`, `collapsible`, `collapsed`, `columns`, `compact`, `aside` | A titled, optionally collapsible block |
| `Fieldset` | `legend`, `columns` | Lightweight grouping with a legend |
| `Grid` | `columns(int)` or `columns(['sm' => 1, 'md' => 2, 'lg' => 4])`, `gap` | Responsive column grid |
| `Columns` | — | Semantic shortcut for `Grid::columns(2)` |
| `Group` | `orientation('horizontal'\|'vertical')` | No visual chrome, just flow control |
| `Tabs` | `tabs(array<Tab>)`, `defaultTab(id)`, `vertical()`/`horizontal()` | Tabbed sections |
| `Tab` | `id`, `label`, `icon`, `badge(int\|Closure)` | One tab inside `Tabs` |

`Section::collapsed()` implicitly turns on `collapsible()` — you don't need to call both.

## Tabs with a badge

```php
Tabs::make()->tabs([
    Tab::make('content', 'Content')->schema([...]),
    Tab::make('seo', 'SEO')->schema([...]),
    Tab::make('comments', 'Comments')
        ->badge(fn ($record) => $record?->comments_count ?? 0)
        ->schema([...]),
])->defaultTab('content');
```

`badge()` accepts an `int` or a `Closure(?Model): int`. A closure that returns something other than an int is silently discarded rather than rendered.

## Conditional visibility at the layout level

Layout components — not just Fields — can hide entire blocks:

```php
Section::make('Admin only')
    ->canSee(fn ($user) => $user?->is_admin)
    ->schema([
        Field::text('internal_id'),
    ]);
```

`isVisibleFor(?Model $record)` evaluates `canSee` before `visibleIf`, same precedence as on Fields.

## Root-level options

```php
Form::make()
    ->schema([...])
    ->columns(3)                       // root grid columns (clamped to >= 1)
    ->model(Post::class)               // model hint for the client
    ->inline()                         // render inline instead of in a modal
    ->disabled();                      // disable every field globally
```

## Generated FormRequests

`php artisan arqel:resource Post --with-form-requests` generates `app/Http/Requests/StorePostRequest.php` and `UpdatePostRequest.php`:

```php
final class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Post::class);
    }

    public function rules(): array
    {
        return app(FieldRulesExtractor::class)->extract(
            app(PostResource::class)->fields()
        );
    }
}
```

These are generated once — Arqel never silently overwrites them on subsequent runs (pass `--force` to regenerate). Edit them freely; `FieldRulesExtractor` also exposes `extractMessages()` and `extractAttributes()` if you want custom validation messages or attribute names sourced from the same Field declarations.

## Anti-patterns

- Redeclaring `fields()` inside `form()` for layout purposes only — reuse the same Field instances via `fields()`/`effectiveFields()` and just wrap them in layout components.
- Full custom `form()` when a single column is enough — omit `form()` entirely and let Arqel auto-derive from `fields()`.
- Nesting a layout component inside a Field — the relationship only goes the other way; layout is always the parent.

## Next steps

- [Fields](/resources/fields) — the input catalog placed inside a form's schema
- [Resource](/resources/resource) — where `form()` is declared
- Full API: [`arqel-dev/form` reference](/reference/php/form)
