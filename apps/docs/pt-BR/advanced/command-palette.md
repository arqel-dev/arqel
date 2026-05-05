# Command palette (Cmd+K)

> Pacote: [`arqel-dev/core`](../../packages/core/) · Tickets: CMDPAL-001..003

## Purpose

A command palette é a barra de ações Cmd+K (Ctrl+K em Linux/Windows) que unifica navegação entre Resources, ações de tema, e qualquer fluxo custom que faça sentido expor via teclado. Cobre RF-N-08.

Funciona como um **registry com providers**: comandos estáticos podem ser registrados ad-hoc; providers são chamados a cada query e devolvem comandos calculados (ex.: lista de Resources do panel).

A ranking é fuzzy — `FuzzyMatcher` aplica scoring estável: exact match > substring > subsequence ordenada > miss. Resultados ordenados por score desc, com tie-break estável por ordem de inserção.

## Setup

O `CommandPaletteServiceProvider` está incluso em `arqel-dev/core` e registra automaticamente os providers built-in. Apenas inclua o `<CommandPalette />` no React shell:

```tsx
import { CommandPalette } from '@arqel-dev/ui/palette';

export default function AppLayout({ children }) {
  return (
    <>
      <CommandPalette />
      {children}
    </>
  );
}
```

A rota `GET /admin/commands?q=...` (`arqel.commands`, middleware `web` + `auth`) responde com `{commands: [...]}` consumido pelo React.

Para registrar comandos custom no boot:

```php
use Arqel\Core\CommandPalette\{Command, CommandRegistry};

public function boot(): void
{
    app(CommandRegistry::class)
        ->register(new Command(
            id: 'inbox:open',
            label: 'Open inbox',
            url: '/admin/inbox',
            description: 'Jump to unread messages',
            category: 'Navigation',
            icon: 'inbox',
        ));
}
```

## Built-in providers

### `NavigationCommandProvider`

Itera `ResourceRegistry::all()` e emite uma `Command` por Resource:

- `id`: `nav:{slug}`
- `label`: `'Go to {pluralLabel}'`
- `url`: `/admin/{slug}`
- `category`: `'Navigation'`
- `icon`: vem de `getNavigationIcon()` (ou `null`)

**Defensivo**: Resources que rebentem em `getSlug()` ou `getPluralLabel()` são silenciosamente saltados; falha em `getNavigationIcon()` apenas downgrade para `icon=null`.

### `ThemeCommandProvider`

3 commands estáticos (sempre devolvidos, filtragem é responsabilidade do registry):

- `theme:light` — icon `sun`
- `theme:dark` — icon `moon`
- `theme:system` — icon `monitor`

Categoria `'Settings'`.

## Custom commands

Há dois caminhos:

### 1. Comando estático (`register(Command)`)

Para itens que não dependem de query ou user.

```php
$registry->register(new Command(
    id: 'docs:arqel',
    label: 'Open Arqel docs',
    url: 'https://arqel.dev/docs',
    category: 'Help',
));
```

### 2. Provider lazy (`registerProvider(CommandProvider|Closure)`)

Para listas calculadas (ex.: últimos 10 customers acessados, atalhos baseados em permissão).

```php
use Arqel\Core\CommandPalette\{Command, CommandProvider};

final class RecentOrdersProvider implements CommandProvider
{
    public function provide(?Authenticatable $user, string $query): array
    {
        return Order::recent(10)->get()->map(fn ($order) => new Command(
            id: "order:{$order->id}",
            label: "Order #{$order->id} — {$order->customer_name}",
            url: "/admin/orders/{$order->id}",
            category: 'Recent',
        ))->all();
    }
}

$registry->registerProvider(new RecentOrdersProvider());
```

Closures são embrulhadas num adapter anónimo automaticamente.

## Endpoint

`GET /admin/commands?q=<query>` — `CommandPaletteController` (single-action invokable):

1. Lê `?q=` do request.
2. Chama `$registry->resolveFor($request->user(), $query)`.
3. Internamente: merge static + providers → `FuzzyMatcher::rank` → cap em 20.
4. Devolve `{commands: [...]}`.

Em empate de score, **commands estáticos vêm antes** dos commands emitidos por providers (ordem de inserção preservada).

## Fuzzy scoring

`FuzzyMatcher::score(string $needle, string $haystack)`:

| Caso | Score |
|---|---|
| Empty needle | 100 |
| Exact case-insensitive match | 95 |
| `str_contains` | 80 |
| Subsequence ordenada | 50 + bónus por runs consecutivos |
| Miss | 0 |

`rank()` aplica `score()` ao label e à description (max), descarta zeros, sort estável (desc por score, asc por índice original), corta no limit (default 20).

## FAQ

**Posso filtrar comandos por user?**
A `Command` value-object não tem `canSee()` — filtragem fica no provider. `provide($user, $query)` recebe o `Authenticatable` para decidir o que emitir.

**O Cmd+K respeita permissões nas Resources auto-registradas?**
Atualmente o `NavigationCommandProvider` emite todas as Resources do registry. Para filtrar, customize: instancie um provider próprio que respeite Policies.

**Posso ter múltiplas palettes (admin + frontend)?**
O endpoint atual é único (`/admin/commands`). Para frontend separado, instancie outro `CommandRegistry` e mount num controller próprio.

## Anti-patterns

- ❌ Provider que faz query DB pesada por keystroke — debounce no React + cache no provider.
- ❌ Command com `url` externa sem indicação visual — adicione `description` clara.
- ❌ Confiar no client para esconder comandos sensíveis — server filtra no `provide()`.

## Related

- [`packages/core/SKILL.md`](../../packages/core/SKILL.md) §CommandPalette
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §CMDPAL-001..003
- [`PLANNING/01-spec-tecnica.md`](../../PLANNING/01-spec-tecnica.md) §RF-N-08
