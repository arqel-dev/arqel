# Actions

Uma **Action** é qualquer operação clicável associada a um Resource: editar, excluir, publicar, exportar, arquivar em lote ou um link para uma página externa. O Arqel as modela em quatro variantes — `RowAction`, `BulkAction`, `ToolbarAction`, `HeaderAction` — todas construídas sobre a mesma class base `Arqel\Actions\Action`.

Referência completa de métodos: [`arqel-dev/actions`](/pt-BR/reference/php/actions).

## O mínimo

Conforme a ADR-019, as actions prontas vêm da factory `Actions`; as customizadas são construídas com a class concreta da variante (`RowAction::make()`, não um alias de factory):

```php
use Arqel\Actions\Actions;

public function actions(): array
{
    return [
        Actions::edit(),      // RowAction → leva para /edit
        Actions::view(),      // RowAction → leva para /show
        Actions::delete(),    // RowAction → DELETE com modal de confirmação
    ];
}
```

Para qualquer coisa customizada:

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

| Class | Onde aparece | Recebe |
|---|---|---|
| `RowAction` | Cada linha da table + a página de detalhe | `$record: Model` |
| `BulkAction` | Toolbar, assim que `selectable()` está ativo e 1+ linhas estão marcadas | `$records: Collection` |
| `ToolbarAction` | Toolbar da table, sempre visível | — |
| `HeaderAction` | Header da página de create/edit/show | `$record: ?Model` |

```php
use Arqel\Actions\Types\{RowAction, BulkAction, ToolbarAction, HeaderAction};
```

Associe-as através do método correspondente na `Table` (`->actions()`, `->bulkActions()`, `->toolbarActions()`) — veja o [guia de Table](/pt-BR/resources/table#actions).

## Modal de confirmação

```php
RowAction::make('archive')
    ->label('Archive')
    ->requiresConfirmation()
    ->modalHeading('Archive this post?')
    ->modalDescription('Can be reverted within 30 days.')
    ->modalColor('warning')
    ->modalConfirmationRequiresText('ARCHIVE')   // o usuário precisa digitar este texto exato
    ->action(fn ($record) => $record->archive());
```

`modalConfirmationRequiresText` mantém o botão de envio desabilitado até que o usuário digite a string exata — reserve isso para operações irreversíveis ou de alto impacto.

## Modal com form

Uma Action pode abrir um modal que coleta dados antes de executar:

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

Os fields do modal são declarados da mesma forma que em `Resource::fields()`. O servidor valida o payload enviado contra `getFormValidationRules()` antes de `action()` rodar.

## Bulk actions com chunking

```php
BulkAction::make('publish_all')
    ->label('Publish selected')
    ->chunkSize(50)                     // default 100
    ->deselectRecordsAfterCompletion()
    ->action(function (Collection $records) {
        $records->each(fn ($r) => $r->publish());
    });
```

`execute(Collection)` percorre os records selecionados em chunks, invocando o callback uma vez por chunk — isso mantém o consumo de memória sob controle em seleções de mais de 10 mil linhas, já que o `ActionController` busca por `whereIn(getKeyName, ids)`.

## Autorização

```php
RowAction::make('approve')
    ->authorize(fn ($user, $record) =>
        $user?->hasRole('manager') && $record->status === 'pending'
    );
```

`canBeExecutedBy(?Authenticatable $user, $record)` é o que o `ActionController` verifica no servidor antes de invocar `action()` — a visibilidade no cliente (`visible()`) é apenas UX, este é o gate de verdade.

## Action como link

Use `url()` em vez de `action()` para fazer o botão navegar em vez de executar lógica no servidor:

```php
ToolbarAction::make('docs')
    ->label('Documentation')
    ->icon('book-open')
    ->url('https://arqel.dev', 'GET');   // abre em uma nova aba automaticamente

RowAction::make('open_pdf')
    ->url(fn ($record) => Storage::url($record->pdf_path), 'GET');
```

`url()` e `action()` são mutuamente exclusivos — definir um limpa o outro.

## Notificações

```php
RowAction::make('publish')
    ->successNotification('Published successfully!')
    ->failureNotification('Failed to publish.');
```

O `HandleArqelInertiaRequests` coloca essas mensagens na prop compartilhada `flash`; o `<FlashContainer>` no cliente as renderiza como toasts.

## Anti-patterns

- Lógica de cliente dentro de `action()` — o callback roda inteiramente no servidor; redirecionamentos, diálogos e outras preocupações de UI pertencem ao frontend, guiados pela resposta da action.
- Declarar `->action(fn () => ...)` sem o parâmetro `$record` em uma `RowAction`/`HeaderAction` — você provavelmente queria ler o record. Se uma action realmente não precisa de record, modele-a como uma `ToolbarAction`.
- Definir `->requiresConfirmation(false)` explicitamente — esse é o default; basta omitir a chamada.

## Próximos passos

- [Table](/pt-BR/resources/table) — associando actions de linha/lote/toolbar a uma table
- [Resource](/pt-BR/resources/resource) — onde as actions são de fato conectadas
- API completa: [referência do `arqel-dev/actions`](/pt-BR/reference/php/actions)
