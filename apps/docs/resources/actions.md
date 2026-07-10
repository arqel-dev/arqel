# Actions

An **Action** is any clickable operation attached to a Resource: edit, delete, publish, export, bulk-archive, or a link to an external page. Arqel models them as four variants — `RowAction`, `BulkAction`, `ToolbarAction`, `HeaderAction` — all built on the same `Arqel\Actions\Action` base class.

Full method reference: [`arqel-dev/actions`](/reference/php/actions).

## The minimum

Per ADR-019, stock actions come from the `Actions` factory; custom ones are built with the concrete variant class (`RowAction::make()`, not a factory alias):

```php
use Arqel\Actions\Actions;

public function actions(): array
{
    return [
        Actions::edit(),      // RowAction → routes to /edit
        Actions::view(),      // RowAction → routes to /show
        Actions::delete(),    // RowAction → DELETE with confirmation modal
    ];
}
```

For anything custom:

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

| Class | Where it appears | Receives |
|---|---|---|
| `RowAction` | Each table row + the detail page | `$record: Model` |
| `BulkAction` | Toolbar, once `selectable()` is on and 1+ rows are checked | `$records: Collection` |
| `ToolbarAction` | Table toolbar, always visible | — |
| `HeaderAction` | Header of the create/edit/show page | `$record: ?Model` |

```php
use Arqel\Actions\Types\{RowAction, BulkAction, ToolbarAction, HeaderAction};
```

Attach them via the corresponding method on `Table` (`->actions()`, `->bulkActions()`, `->toolbarActions()`) — see the [Table guide](/resources/table#actions).

## Confirmation modal

```php
RowAction::make('archive')
    ->label('Archive')
    ->requiresConfirmation()
    ->modalHeading('Archive this post?')
    ->modalDescription('Can be reverted within 30 days.')
    ->modalColor('warning')
    ->modalConfirmationRequiresText('ARCHIVE')   // user must type this exact text
    ->action(fn ($record) => $record->archive());
```

`modalConfirmationRequiresText` keeps the submit button disabled until the user types the exact string — reserve it for irreversible or high-blast-radius operations.

## Form modal

An Action can open a modal that collects data before executing:

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

The modal fields are declared the same way as `Resource::fields()`. The server validates the submitted payload against `getFormValidationRules()` before `action()` runs.

## Bulk actions with chunking

```php
BulkAction::make('publish_all')
    ->label('Publish selected')
    ->chunkSize(50)                     // default 100
    ->deselectRecordsAfterCompletion()
    ->action(function (Collection $records) {
        $records->each(fn ($r) => $r->publish());
    });
```

`execute(Collection)` iterates the selected records in chunks, invoking the callback once per chunk — this keeps memory bounded on selections of 10k+ rows, since `ActionController` fetches by `whereIn(getKeyName, ids)`.

## Authorization

```php
RowAction::make('approve')
    ->authorize(fn ($user, $record) =>
        $user?->hasRole('manager') && $record->status === 'pending'
    );
```

`canBeExecutedBy(?Authenticatable $user, $record)` is what `ActionController` checks server-side before invoking `action()` — visibility on the client (`visible()`) is UX only, this is the actual gate.

## Action as a link

Use `url()` instead of `action()` to make the button navigate rather than execute server logic:

```php
ToolbarAction::make('docs')
    ->label('Documentation')
    ->icon('book-open')
    ->url('https://arqel.dev', 'GET');   // opens in a new tab automatically

RowAction::make('open_pdf')
    ->url(fn ($record) => Storage::url($record->pdf_path), 'GET');
```

`url()` and `action()` are mutually exclusive — setting one clears the other.

## Notifications

```php
RowAction::make('publish')
    ->successNotification('Published successfully!')
    ->failureNotification('Failed to publish.');
```

`HandleArqelInertiaRequests` flashes these messages into the shared `flash` prop; `<FlashContainer>` on the client renders them as toasts.

## Anti-patterns

- Client-side logic inside `action()` — the callback runs entirely server-side; redirects, dialogs, and other UI concerns belong on the frontend, driven by the action's response.
- Declaring `->action(fn () => ...)` without the `$record` parameter on a `RowAction`/`HeaderAction` — you likely meant to read the record. If an action genuinely needs no record, model it as a `ToolbarAction` instead.
- Setting `->requiresConfirmation(false)` explicitly — it's the default; just omit the call.

## Next steps

- [Table](/resources/table) — attaching row/bulk/toolbar actions to a table
- [Resource](/resources/resource) — where actions ultimately get wired up
- Full API: [`arqel-dev/actions` reference](/reference/php/actions)
