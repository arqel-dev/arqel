# Actions

Una **Action** es cualquier operación clicable asociada a un Resource: editar, eliminar, publicar, exportar, archivar en lote o enlazar a una página externa. Arqel las modela como cuatro variantes — `RowAction`, `BulkAction`, `ToolbarAction`, `HeaderAction` — todas construidas sobre la misma clase base `Arqel\Actions\Action`.

Referencia completa de métodos: [`arqel-dev/actions`](/es/reference/php/actions).

## Lo mínimo

Según el ADR-019, las actions de serie vienen de la factory `Actions`; las personalizadas se construyen con la clase concreta de la variante (`RowAction::make()`, no un alias de factory):

```php
use Arqel\Actions\Actions;

public function actions(): array
{
    return [
        Actions::edit(),      // RowAction → dirige a /edit
        Actions::view(),      // RowAction → dirige a /show
        Actions::delete(),    // RowAction → DELETE con modal de confirmación
    ];
}
```

Para cualquier cosa personalizada:

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

| Clase | Dónde aparece | Recibe |
|---|---|---|
| `RowAction` | Cada fila de la tabla + la página de detalle | `$record: Model` |
| `BulkAction` | Toolbar, una vez que `selectable()` está activo y hay 1 o más filas marcadas | `$records: Collection` |
| `ToolbarAction` | Toolbar de la tabla, siempre visible | — |
| `HeaderAction` | Header de la página de create/edit/show | `$record: ?Model` |

```php
use Arqel\Actions\Types\{RowAction, BulkAction, ToolbarAction, HeaderAction};
```

Adjúntalas mediante el método correspondiente de `Table` (`->actions()`, `->bulkActions()`, `->toolbarActions()`) — consulta la [guía de Table](/es/resources/table#actions).

## Modal de confirmación

```php
RowAction::make('archive')
    ->label('Archive')
    ->requiresConfirmation()
    ->modalHeading('Archive this post?')
    ->modalDescription('Can be reverted within 30 days.')
    ->modalColor('warning')
    ->modalConfirmationRequiresText('ARCHIVE')   // el usuario debe escribir este texto exacto
    ->action(fn ($record) => $record->archive());
```

`modalConfirmationRequiresText` mantiene el botón de envío deshabilitado hasta que el usuario escriba la cadena exacta — resérvalo para operaciones irreversibles o de alto impacto.

## Modal con formulario

Una Action puede abrir un modal que recoge datos antes de ejecutarse:

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

Los fields del modal se declaran igual que en `Resource::fields()`. El servidor valida el payload enviado contra `getFormValidationRules()` antes de que se ejecute `action()`.

## Bulk actions con troceado

```php
BulkAction::make('publish_all')
    ->label('Publish selected')
    ->chunkSize(50)                     // por defecto 100
    ->deselectRecordsAfterCompletion()
    ->action(function (Collection $records) {
        $records->each(fn ($r) => $r->publish());
    });
```

`execute(Collection)` itera los registros seleccionados en trozos, invocando el callback una vez por trozo — esto mantiene acotada la memoria en selecciones de más de 10 000 filas, ya que `ActionController` recupera los registros con `whereIn(getKeyName, ids)`.

## Autorización

```php
RowAction::make('approve')
    ->authorize(fn ($user, $record) =>
        $user?->hasRole('manager') && $record->status === 'pending'
    );
```

`canBeExecutedBy(?Authenticatable $user, $record)` es lo que `ActionController` verifica del lado del servidor antes de invocar `action()` — la visibilidad en el cliente (`visible()`) es solo UX, esta es la verdadera puerta de control.

## Action como enlace

Usa `url()` en lugar de `action()` para que el botón navegue en vez de ejecutar lógica en el servidor:

```php
ToolbarAction::make('docs')
    ->label('Documentation')
    ->icon('book-open')
    ->url('https://arqel.dev', 'GET');   // se abre en una pestaña nueva automáticamente

RowAction::make('open_pdf')
    ->url(fn ($record) => Storage::url($record->pdf_path), 'GET');
```

`url()` y `action()` son mutuamente excluyentes — establecer uno limpia el otro.

## Notificaciones

```php
RowAction::make('publish')
    ->successNotification('Published successfully!')
    ->failureNotification('Failed to publish.');
```

`HandleArqelInertiaRequests` vuelca estos mensajes en la prop compartida `flash`; `<FlashContainer>` en el cliente los renderiza como toasts.

## Antipatrones

- Lógica del lado del cliente dentro de `action()` — el callback se ejecuta enteramente en el servidor; las redirecciones, los diálogos y otras preocupaciones de UI pertenecen al frontend, guiados por la respuesta de la action.
- Declarar `->action(fn () => ...)` sin el parámetro `$record` en una `RowAction`/`HeaderAction` — lo más probable es que quisieras leer el registro. Si una action realmente no necesita ningún registro, modélala como una `ToolbarAction`.
- Establecer `->requiresConfirmation(false)` explícitamente — es el valor por defecto; simplemente omite la llamada.

## Próximos pasos

- [Table](/es/resources/table) — adjuntar actions de fila, en lote y de toolbar a una tabla
- [Resource](/es/resources/resource) — dónde acaban conectándose las actions
- API completa: [referencia de `arqel-dev/actions`](/es/reference/php/actions)
