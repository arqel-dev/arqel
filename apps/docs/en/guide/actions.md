# Actions

An **Action** is any clickable button that triggers behavior — edit, delete, publish, export, bulk archive. Arqel splits them into 4 variants: `RowAction`, `BulkAction`, `ToolbarAction`, `HeaderAction`.

## The minimum

```php
use Arqel\Actions\Actions;

public function actions(): array
{
    return [
        Actions::edit(),                 // RowAction → routes to /edit
        Actions::view(),                 // RowAction → routes to /show
        Actions::delete(),               // RowAction → DELETE with confirm
    ];
}
```

`Actions::*` is the pre-configured factory. For custom:

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

## Variants

| Type | Where it appears | Receives |
|---|---|---|
| `RowAction` | Each table row + detail page | `$record: Model` |
| `BulkAction` | Toolbar when `selectable + 1+ selected` | `$records: Collection` |
| `ToolbarAction` | Table toolbar (always visible) | — |
| `HeaderAction` | Header of the create/edit/show page | `$record: ?Model` |

```php
use Arqel\Actions\Types\{RowAction, BulkAction, ToolbarAction, HeaderAction};
```

## Confirmation modal

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

`modalConfirmationRequiresText` requires the user to type the exact text before submit becomes enabled — useful for destructive ops.

## Form modal

Action that opens a modal with fields:

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

The modal renders `<FormRenderer>` with the declared fields. Client-side validation via `ValidationBridge` Zod (Phase 2 enhancement).

## Bulk with chunking

```php
BulkAction::make('publish_all')
    ->label('Publish selected')
    ->chunkSize(50)                                  // default 100
    ->deselectRecordsAfterCompletion()
    ->action(function (Collection $records) {
        $records->each(fn ($r) => $r->publish());
    });
```

`Action::execute(Collection)` iterates in chunks calling the callback once per chunk — avoids memory blow-up on selects of 10k+.

## Authorization

```php
RowAction::make('approve')
    ->authorize(fn ($user, $record) =>
        $user?->hasRole('manager') && $record->status === 'pending'
    );
```

`canBeExecutedBy(?Authenticatable, $record)` is the oracle that `ActionController` consults before executing.

## Action as a link

```php
ToolbarAction::make('docs')
    ->label('Documentation')
    ->icon('book-open')
    ->url('https://arqel.dev', 'GET');               // opens in another tab automatically

RowAction::make('open_pdf')
    ->url(fn ($record) => Storage::url($record->pdf_path), 'GET');
```

`url()` and `action()` are XOR — calling one clears the other.

## Notifications

```php
RowAction::make('publish')
    ->successNotification('Published successfully!')
    ->failureNotification('Failed to publish.');
```

The `HandleArqelInertiaRequests` middleware flashes those messages; `<FlashContainer>` renders them as toasts.

## Anti-patterns

- UI logic in `action()` — the callback runs server-side; redirects/dialogs belong on the client
- `->action(...)` without reading `$record` — you forgot the argument. For Actions without a record, use `ToolbarAction`
- Explicit `requiresConfirmation: false` — just omit it; default is already false

## Next steps

- [Auth](/guide/auth) — Policies + ability registry
- API reference: [`packages/actions/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/actions/SKILL.md)
