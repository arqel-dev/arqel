# Form

`Arqel\Form\Form` es el builder fluido detrás de las páginas de create/edit de un Resource. Organiza los Fields en secciones, grillas, pestañas y otros componentes de layout, y luego se serializa a un esquema que el `<FormRenderer>` de `@arqel-dev/ui` renderiza en el cliente.

Igual que `table()`, el método `form()` de un Resource es opcional. Cuando no lo declaras, Arqel renderiza `Resource::fields()` en un layout directo de una sola columna. Recurre a un `Form` personalizado cuando necesites layouts de varias columnas, secciones, pestañas o visibilidad condicional a nivel de layout.

Referencia completa de métodos: [`arqel-dev/form`](/es/reference/php/form).

## Lo mínimo

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
            ->aside()   // se renderiza como un panel lateral en escritorio
            ->schema([
                Field::select('status')->options(['draft' => 'Draft', 'published' => 'Published']),
                Field::dateTime('published_at'),
            ]),
    ])->columns(3);
}
```

`schema()` acepta una mezcla heterogénea de Fields y componentes de layout — anida Fields dentro de componentes de layout libremente, pero no al revés.

## Componentes de layout

`Arqel\Form\Layout\Component` es la base abstracta compartida — todos los componentes de layout admiten `$columnSpan`, `$visibleIf` y `$canSee`.

| Clase | Setters adicionales | Caso de uso |
|---|---|---|
| `Section` | `heading`, `description`, `icon`, `collapsible`, `collapsed`, `columns`, `compact`, `aside` | Un bloque con título, opcionalmente plegable |
| `Fieldset` | `legend`, `columns` | Agrupación ligera con una leyenda |
| `Grid` | `columns(int)` o `columns(['sm' => 1, 'md' => 2, 'lg' => 4])`, `gap` | Grilla de columnas responsiva |
| `Columns` | — | Atajo semántico para `Grid::columns(2)` |
| `Group` | `orientation('horizontal'\|'vertical')` | Sin adornos visuales, solo control de flujo |
| `Tabs` | `tabs(array<Tab>)`, `defaultTab(id)`, `vertical()`/`horizontal()` | Secciones en pestañas |
| `Tab` | `id`, `label`, `icon`, `badge(int\|Closure)` | Una pestaña dentro de `Tabs` |

`Section::collapsed()` activa implícitamente `collapsible()` — no necesitas llamar a ambos.

## Pestañas con badge

```php
Tabs::make()->tabs([
    Tab::make('content', 'Content')->schema([...]),
    Tab::make('seo', 'SEO')->schema([...]),
    Tab::make('comments', 'Comments')
        ->badge(fn ($record) => $record?->comments_count ?? 0)
        ->schema([...]),
])->defaultTab('content');
```

`badge()` acepta un `int` o una `Closure(?Model): int`. Una closure que devuelva algo distinto de un int se descarta silenciosamente en lugar de renderizarse.

## Visibilidad condicional a nivel de layout

Los componentes de layout — no solo los Fields — pueden ocultar bloques enteros:

```php
Section::make('Admin only')
    ->canSee(fn ($user) => $user?->is_admin)
    ->schema([
        Field::text('internal_id'),
    ]);
```

`isVisibleFor(?Model $record)` evalúa `canSee` antes que `visibleIf`, con la misma precedencia que en los Fields.

## Opciones a nivel raíz

```php
Form::make()
    ->schema([...])
    ->columns(3)                       // columnas de la grilla raíz (acotadas a >= 1)
    ->model(Post::class)               // pista del modelo para el cliente
    ->inline()                         // renderiza en línea en lugar de en un modal
    ->disabled();                      // deshabilita todos los fields globalmente
```

## FormRequests generados

`php artisan arqel:resource Post --with-form-requests` genera `app/Http/Requests/StorePostRequest.php` y `UpdatePostRequest.php`:

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

Estos se generan una sola vez — Arqel nunca los sobrescribe silenciosamente en ejecuciones posteriores (pasa `--force` para regenerarlos). Edítalos libremente; `FieldRulesExtractor` también expone `extractMessages()` y `extractAttributes()` si quieres mensajes de validación o nombres de atributo personalizados tomados de esas mismas declaraciones de Field.

## Antipatrones

- Volver a declarar `fields()` dentro de `form()` solo por motivos de layout — reutiliza las mismas instancias de Field mediante `fields()`/`effectiveFields()` y limítate a envolverlas en componentes de layout.
- Un `form()` totalmente personalizado cuando basta con una sola columna — omite `form()` por completo y deja que Arqel lo autoderive desde `fields()`.
- Anidar un componente de layout dentro de un Field — la relación solo va en el otro sentido; el layout siempre es el padre.

## Próximos pasos

- [Fields](/es/resources/fields) — el catálogo de inputs que se coloca dentro del schema de un formulario
- [Resource](/es/resources/resource) — dónde se declara `form()`
- API completa: [referencia de `arqel-dev/form`](/es/reference/php/form)
