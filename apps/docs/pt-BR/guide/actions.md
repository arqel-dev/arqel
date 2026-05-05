# Actions

Uma **Action** é qualquer botão clicável que dispara comportamento — edit, delete, publicar, exportar, archive bulk. Arqel separa em 4 variantes: `RowAction`, `BulkAction`, `ToolbarAction`, `HeaderAction`.

## O mínimo

```php
use Arqel\Actions\Actions;

public function actions(): array
{
    return [
        Actions::edit(),                 // RowAction → routa para /edit
        Actions::view(),                 // RowAction → routa para /show
        Actions::delete(),               // RowAction → DELETE com confirm
    ];
}
```

`Actions::*` é a factory pré-configurada. Para custom:

```php
use Arqel\Actions\Types\RowAction;

RowAction::make('publish')
    ->label('Publicar')
    ->icon('check-circle')
    ->color('success')
    ->visible(fn ($record) => $record->status === 'draft')
    ->action(fn ($record) => $record->update(['status' => 'published']))
    ->successNotification('Post publicado!');
```

## Variantes

| Tipo | Onde aparece | Recebe |
|---|---|---|
| `RowAction` | Cada linha da table + página detail | `$record: Model` |
| `BulkAction` | Toolbar quando `selectable + 1+ selecionado` | `$records: Collection` |
| `ToolbarAction` | Toolbar da table (sempre visível) | — |
| `HeaderAction` | Header da página create/edit/show | `$record: ?Model` |

```php
use Arqel\Actions\Types\{RowAction, BulkAction, ToolbarAction, HeaderAction};
```

## Confirmation modal

```php
RowAction::make('archive')
    ->label('Arquivar')
    ->requiresConfirmation()
    ->modalHeading('Arquivar este post?')
    ->modalDescription('Pode ser revertido em até 30 dias.')
    ->modalColor('warning')
    ->modalConfirmationRequiresText('ARCHIVE')   // type-to-confirm
    ->action(fn ($record) => $record->archive());
```

`modalConfirmationRequiresText` exige que o user digite o texto exato antes do submit ficar enabled — útil para destructive ops.

## Form modal

Action que abre um modal com fields:

```php
RowAction::make('reject')
    ->label('Rejeitar')
    ->color('destructive')
    ->form([
        Field::textarea('reason')->required()->maxLength(500),
    ])
    ->modalSize('lg')
    ->action(function ($record, array $data) {
        $record->reject($data['reason']);
    });
```

O modal renderiza `<FormRenderer>` com os fields declarados. Validação client-side via `ValidationBridge` Zod (Phase 2 enhancement).

## Bulk com chunking

```php
BulkAction::make('publish_all')
    ->label('Publicar selecionados')
    ->chunkSize(50)                                  // default 100
    ->deselectRecordsAfterCompletion()
    ->action(function (Collection $records) {
        $records->each(fn ($r) => $r->publish());
    });
```

`Action::execute(Collection)` itera em chunks chamando o callback uma vez por chunk — evita memory blow-up em selects de 10k+.

## Authorization

```php
RowAction::make('approve')
    ->authorize(fn ($user, $record) =>
        $user?->hasRole('manager') && $record->status === 'pending'
    );
```

`canBeExecutedBy(?Authenticatable, $record)` é o oracle que o `ActionController` consulta antes de executar.

## Action como link

```php
ToolbarAction::make('docs')
    ->label('Documentação')
    ->icon('book-open')
    ->url('https://arqel.dev', 'GET');               // abre noutra tab automaticamente

RowAction::make('open_pdf')
    ->url(fn ($record) => Storage::url($record->pdf_path), 'GET');
```

`url()` e `action()` são XOR — chamar um limpa o outro.

## Notifications

```php
RowAction::make('publish')
    ->successNotification('Publicado com sucesso!')
    ->failureNotification('Falha ao publicar.');
```

`HandleArqelInertiaRequests` middleware faz flash dessas mensagens; `<FlashContainer>` renderiza-as como toasts.

## Anti-patterns

- ❌ **Lógica de UI em `action()`** — o callback é server-side; redirecionamentos/dialogs são do client
- ❌ **`->action(...)`** sem ler `$record` — esqueceu o argumento. Para Actions sem record, use `ToolbarAction`
- ❌ **`requiresConfirmation: false`** explicit — basta omitir; default já é false

## Próximos passos

- [Auth](/pt-BR/guide/auth) — Policies + ability registry
- API reference: [`packages/actions/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/actions/SKILL.md)
