# Command palette (Cmd+K)

> Package: [`arqel-dev/core`](../../packages/core/) · Tickets: CMDPAL-001..003

## Purpose

The command palette is the Cmd+K (Ctrl+K on Linux/Windows) action bar that unifies navigation across Resources, theme actions, and any custom flow worth exposing via keyboard. It covers RF-N-08.

It works as a **registry with providers**: static commands can be registered ad-hoc; providers are called on every query and return computed commands (e.g., the panel's list of Resources).

Ranking is fuzzy — `FuzzyMatcher` applies stable scoring: exact match > substring > ordered subsequence > miss. Results ordered by score desc, with stable tie-break by insertion order.

## Setup

The `CommandPaletteServiceProvider` ships with `arqel-dev/core` and registers the built-in providers automatically. Just include `<CommandPalette />` in the React shell:

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

The route `GET /admin/commands?q=...` (`arqel.commands`, middleware `web` + `auth`) responds with `{commands: [...]}` consumed by React.

To register custom commands at boot:

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

Iterates `ResourceRegistry::all()` and emits one `Command` per Resource:

- `id`: `nav:{slug}`
- `label`: `'Go to {pluralLabel}'`
- `url`: `/admin/{slug}`
- `category`: `'Navigation'`
- `icon`: comes from `getNavigationIcon()` (or `null`)

**Defensive**: Resources that throw in `getSlug()` or `getPluralLabel()` are silently skipped; failure in `getNavigationIcon()` only downgrades to `icon=null`.

### `ThemeCommandProvider`

3 static commands (always returned, filtering is the registry's responsibility):

- `theme:light` — icon `sun`
- `theme:dark` — icon `moon`
- `theme:system` — icon `monitor`

Category `'Settings'`.

## Custom commands

There are two paths:

### 1. Static command (`register(Command)`)

For items that don't depend on query or user.

```php
$registry->register(new Command(
    id: 'docs:arqel',
    label: 'Open Arqel docs',
    url: 'https://arqel.dev/docs',
    category: 'Help',
));
```

### 2. Lazy provider (`registerProvider(CommandProvider|Closure)`)

For computed lists (e.g., last 10 customers accessed, permission-based shortcuts).

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

Closures are wrapped in an anonymous adapter automatically.

## Endpoint

`GET /admin/commands?q=<query>` — `CommandPaletteController` (single-action invokable):

1. Reads `?q=` from the request.
2. Calls `$registry->resolveFor($request->user(), $query)`.
3. Internally: merge static + providers → `FuzzyMatcher::rank` → cap at 20.
4. Returns `{commands: [...]}`.

On score ties, **static commands come before** commands emitted by providers (insertion order preserved).

## Fuzzy scoring

`FuzzyMatcher::score(string $needle, string $haystack)`:

| Case | Score |
|---|---|
| Empty needle | 100 |
| Exact case-insensitive match | 95 |
| `str_contains` | 80 |
| Ordered subsequence | 50 + bonus for consecutive runs |
| Miss | 0 |

`rank()` applies `score()` to label and description (max), drops zeros, stable sort (desc by score, asc by original index), caps at the limit (default 20).

## FAQ

**Can I filter commands by user?**
The `Command` value-object has no `canSee()` — filtering lives in the provider. `provide($user, $query)` receives the `Authenticatable` to decide what to emit.

**Does Cmd+K respect permissions on auto-registered Resources?**
Currently the `NavigationCommandProvider` emits all Resources from the registry. To filter, customize: instantiate your own provider that respects Policies.

**Can I have multiple palettes (admin + frontend)?**
The current endpoint is unique (`/admin/commands`). For a separate frontend, instantiate another `CommandRegistry` and mount it on its own controller.

## Anti-patterns

- ❌ A provider that runs heavy DB queries on every keystroke — debounce on React + cache in the provider.
- ❌ A command with an external `url` and no visual hint — add a clear `description`.
- ❌ Trusting the client to hide sensitive commands — the server filters in `provide()`.

## Related

- [`packages/core/SKILL.md`](../../packages/core/SKILL.md) §CommandPalette
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §CMDPAL-001..003
- [`PLANNING/01-spec-tecnica.md`](../../PLANNING/01-spec-tecnica.md) §RF-N-08
