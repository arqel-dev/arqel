# Paleta de comandos (Cmd+K)

> Paquete: [`arqel-dev/core`](../../packages/core/) · Tickets: CMDPAL-001..003

## Propósito

La paleta de comandos es la barra de Action Cmd+K (Ctrl+K en Linux/Windows) que unifica la navegación entre Resources, acciones de theme y cualquier flujo custom que valga la pena exponer por teclado. Cubre RF-N-08.

Funciona como un **registro con providers**: los comandos estáticos pueden registrarse ad-hoc; los providers se llaman en cada query y devuelven comandos computados (e.g., la lista de Resources del panel).

El ranking es fuzzy — `FuzzyMatcher` aplica scoring estable: exact match > substring > subsecuencia ordenada > miss. Resultados ordenados por score desc, con tie-break estable por orden de inserción.

## Setup

El `CommandPaletteServiceProvider` viene con `arqel-dev/core` y registra los providers built-in automáticamente. Solo incluye `<CommandPalette />` en el shell React:

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

La ruta `GET /admin/commands?q=...` (`arqel.commands`, middleware `web` + `auth`) responde con `{commands: [...]}` consumido por React.

Para registrar comandos custom en boot:

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

## Providers built-in

### `NavigationCommandProvider`

Itera `ResourceRegistry::all()` y emite un `Command` por Resource:

- `id`: `nav:{slug}`
- `label`: `'Go to {pluralLabel}'`
- `url`: `/admin/{slug}`
- `category`: `'Navigation'`
- `icon`: viene de `getNavigationIcon()` (o `null`)

**Defensivo**: los Resources que lanzan en `getSlug()` o `getPluralLabel()` se omiten silenciosamente; un fallo en `getNavigationIcon()` solo degrada a `icon=null`.

### `ThemeCommandProvider`

3 comandos estáticos (siempre devueltos, el filtrado es responsabilidad del registry):

- `theme:light` — icon `sun`
- `theme:dark` — icon `moon`
- `theme:system` — icon `monitor`

Categoría `'Settings'`.

## Comandos custom

Hay dos rutas:

### 1. Comando estático (`register(Command)`)

Para items que no dependen de query o usuario.

```php
$registry->register(new Command(
    id: 'docs:arqel',
    label: 'Open Arqel docs',
    url: 'https://arqel.dev/docs',
    category: 'Help',
));
```

### 2. Provider lazy (`registerProvider(CommandProvider|Closure)`)

Para listas computadas (e.g., últimos 10 customers accedidos, atajos basados en permisos).

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

Las Closures se envuelven en un adapter anónimo automáticamente.

## Endpoint

`GET /admin/commands?q=<query>` — `CommandPaletteController` (single-action invokable):

1. Lee `?q=` del request.
2. Llama `$registry->resolveFor($request->user(), $query)`.
3. Internamente: merge estáticos + providers → `FuzzyMatcher::rank` → cap a 20.
4. Devuelve `{commands: [...]}`.

En empates de score, **los comandos estáticos vienen antes** de los emitidos por providers (orden de inserción preservado).

## Scoring fuzzy

`FuzzyMatcher::score(string $needle, string $haystack)`:

| Caso | Score |
|---|---|
| Needle vacío | 100 |
| Match case-insensitive exacto | 95 |
| `str_contains` | 80 |
| Subsecuencia ordenada | 50 + bonus por runs consecutivos |
| Miss | 0 |

`rank()` aplica `score()` al label y description (max), descarta ceros, sort estable (desc por score, asc por índice original), cap al límite (default 20).

## FAQ

**¿Puedo filtrar comandos por usuario?**
El value-object `Command` no tiene `canSee()` — el filtrado vive en el provider. `provide($user, $query)` recibe el `Authenticatable` para decidir qué emitir.

**¿Cmd+K respeta permisos en Resources auto-registrados?**
Actualmente el `NavigationCommandProvider` emite todos los Resources del registry. Para filtrar, personaliza: instancia tu propio provider que respete las Policies.

**¿Puedo tener múltiples paletas (admin + frontend)?**
El endpoint actual es único (`/admin/commands`). Para un frontend separado, instancia otro `CommandRegistry` y móntalo en su propio controlador.

## Anti-patrones

- ❌ Un provider que corre queries pesadas a la DB en cada keystroke — debounce en React + cache en el provider.
- ❌ Un comando con `url` externa y sin pista visual — añade un `description` claro.
- ❌ Confiar en el cliente para ocultar comandos sensibles — el servidor filtra en `provide()`.

## Relacionado

- [`packages/core/SKILL.md`](../../packages/core/SKILL.md) §CommandPalette
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §CMDPAL-001..003
- [`PLANNING/01-spec-tecnica.md`](../../PLANNING/01-spec-tecnica.md) §RF-N-08
