# Actions

Una **Action** es cualquier botón clicable que dispara un comportamiento — editar, borrar, publicar, exportar, archivar en bulk. Arqel las divide en 4 variantes: `RowAction`, `BulkAction`, `ToolbarAction`, `HeaderAction`.

## Lo mínimo

```php
use Arqel\Actions\Actions;

public function actions(): array
{
    return [
        Actions::edit(),                 // RowAction → enruta a /edit
        Actions::view(),                 // RowAction → enruta a /show
        Actions::delete(),               // RowAction → DELETE con confirmación
    ];
}
```

`Actions::*` es la factory pre-configurada. Para personalizadas:

```php
use Arqel\Actions\Types\RowAction;

RowAction::make('publish')
    ->label('Publish')
    ->icon('check-circle')
    ->color('success')
    ->visible(fn ($record) => $record->status === 'draft')
    ->action(fn ($record) => $record->update(['status' => 'published']))
    ->successNotification('Post published!');
```

## Variantes

| Tipo | Dónde aparece | Recibe |
|---|---|---|
| `RowAction` | Cada fila de la tabla + página de detalle | `$record: Model` |
| `BulkAction` | Toolbar cuando `selectable + 1+ seleccionados` | `$records: Collection` |
| `ToolbarAction` | Toolbar de la tabla (siempre visible) | — |
| `HeaderAction` | Header de la página create/edit/show | `$record: ?Model` |

```php
use Arqel\Actions\Types\{RowAction, BulkAction, ToolbarAction, HeaderAction};
```

## Modal de confirmación

```php
RowAction::make('archive')
    ->label('Archive')
    ->requiresConfirmation()
    ->modalHeading('Archive this post?')
    ->modalDescription('Can be reverted within 30 days.')
    ->modalColor('warning')
    ->modalConfirmationRequiresText('ARCHIVE')   // type-to-confirm
    ->action(fn ($record) => $record->archive());
```

`modalConfirmationRequiresText` requiere que el usuario escriba el texto exacto antes de que el submit se habilite — útil para operaciones destructivas.

## Modal de form

Action que abre un modal con fields:

```php
RowAction::make('reject')
    ->label('Reject')
    ->color('destructive')
    ->form([
        Field::textarea('reason')->required()->maxLength(500),
    ])
    ->modalSize('lg')
    ->action(function ($record, array $data) {
        $record->reject($data['reason']);
    });
```

El modal renderiza `<FormRenderer>` con los fields declarados. Validación del lado del cliente vía Zod de `ValidationBridge` (mejora de Fase 2).

## Bulk con chunking

```php
BulkAction::make('publish_all')
    ->label('Publish selected')
    ->chunkSize(50)                                  // por defecto 100
    ->deselectRecordsAfterCompletion()
    ->action(function (Collection $records) {
        $records->each(fn ($r) => $r->publish());
    });
```

`Action::execute(Collection)` itera en chunks llamando al callback una vez por chunk — evita explosiones de memoria en selects de 10k+.

## Autorización

```php
RowAction::make('approve')
    ->authorize(fn ($user, $record) =>
        $user?->hasRole('manager') && $record->status === 'pending'
    );
```

`canBeExecutedBy(?Authenticatable, $record)` es el oráculo que `ActionController` consulta antes de ejecutar.

## Action como enlace

```php
ToolbarAction::make('docs')
    ->label('Documentation')
    ->icon('book-open')
    ->url('https://arqel.dev', 'GET');               // se abre en otra pestaña automáticamente

RowAction::make('open_pdf')
    ->url(fn ($record) => Storage::url($record->pdf_path), 'GET');
```

`url()` y `action()` son XOR — llamar a uno limpia el otro.

## Notificaciones

```php
RowAction::make('publish')
    ->successNotification('Published successfully!')
    ->failureNotification('Failed to publish.');
```

El middleware `HandleArqelInertiaRequests` flashea esos mensajes; `<FlashContainer>` los renderiza como toasts.

## Anti-patrones

- Lógica de UI en `action()` — el callback corre server-side; redirects/dialogs van en el cliente
- `->action(...)` sin leer `$record` — olvidaste el argumento. Para Actions sin registro, usa `ToolbarAction`
- `requiresConfirmation: false` explícito — simplemente omítelo; el default ya es false

## Próximos pasos

- [Auth](/es/guide/auth) — Policies + ability registry
- Referencia API: [`packages/actions/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/actions/SKILL.md)
