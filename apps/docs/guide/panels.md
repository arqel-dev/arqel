# Panels

Um **Panel** é o agrupador raiz de uma instância Arqel — um conjunto de Resources, Widgets, middleware, branding e path. Você pode ter um único panel `/admin` (caso típico), ou múltiplos painéis por persona — `/admin`, `/staff`, `/partners` — cada um com Resources e auth próprios.

## O mínimo

Em `app/Providers/ArqelServiceProvider.php` (criado por `php artisan arqel:install`):

```php
namespace App\Providers;

use App\Arqel\Resources\UserResource;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Support\ServiceProvider;

final class ArqelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving(PanelRegistry::class, function (PanelRegistry $panels): void {
            $panels->panel('admin')
                ->path('admin')
                ->brand('Acme Admin')
                ->resources([UserResource::class])
                ->middleware(['web', 'auth']);
        });
    }
}
```

Isso registra um panel `admin` em `/admin` com 1 Resource e auth ativa.

## API fluente

| Método | Tipo | Descrição |
|---|---|---|
| `path(string)` | `string` | Path raiz do panel (sem barra inicial) |
| `brand(string)` | `string` | Texto/título exibido no Topbar |
| `theme(string)` | `string` | Cor primária ou ID de theme custom |
| `primaryColor(string)` | `string` | Atalho para `--color-arqel-primary` |
| `darkMode(bool)` | `bool` | Ativa toggle de dark mode no Topbar |
| `middleware(array)` | `array` | Middleware aplicada a todas as rotas do panel |
| `resources(array)` | `array<class-string>` | Lista de classes Resource |
| `widgets(array)` | `array<class-string>` | Widgets exibidos no dashboard |
| `navigationGroups(array)` | `array<NavigationGroup>` | Customiza a sidebar |
| `authGuard(string)` | `string` | Guard alternativa (default `web`) |
| `tenant(class-string)` | `class-string` | Tenant resolver (Phase 2) |

## Multi-panel

```php
$panels->panel('admin')
    ->path('admin')
    ->brand('Acme — Internal')
    ->resources([UserResource::class, OrderResource::class])
    ->middleware(['web', 'auth', 'role:staff']);

$panels->panel('partners')
    ->path('partners')
    ->brand('Acme — Partner Portal')
    ->resources([CommissionResource::class])
    ->middleware(['web', 'auth', 'role:partner']);
```

Cada panel é independente — Resources, sidebar, theme e middleware separados. O `arqel:install` cria apenas `admin` por defeito; adicione mais conforme precisar.

## Como o panel é resolvido

`Arqel\Core\Panel\PanelRegistry` é um singleton populado no boot. Em runtime, `HandleArqelInertiaRequests` middleware chama `PanelRegistry::setCurrent($id)` baseado no path da request, e `panel` aparece como shared prop em `usePage().props.panel` no React.

```tsx
import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@arqel-dev/types';

const { panel } = usePage<SharedProps>().props;
console.log(panel.id);    // 'admin'
console.log(panel.brand); // 'Acme Admin'
console.log(panel.path);  // 'admin'
```

## Anti-patterns

- ❌ **Hardcode de URL** no front-end — use `route('arqel.resources.index', { resource: 'users' })` (Ziggy) ou os links gerados pela `Sidebar` automaticamente
- ❌ **Compartilhar Resources entre panels** — em vez disso, declare `Resource::canBeSeenIn(panel)` (Phase 2) ou duplique a class para diferenciar comportamento

## Próximos passos

- [Resources](/guide/resources) — declarar models como CRUDs
- [Auth](/guide/auth) — Policies + middleware do panel
- API reference: [PHP overview](/reference/php-overview)
