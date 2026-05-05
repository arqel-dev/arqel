# Panels

Un **Panel** es la agrupación raíz de una instancia de Arqel — un conjunto de Resources, Widgets, middleware, branding y path. Puedes tener un único panel `/admin` (el caso típico), o múltiples panels por persona — `/admin`, `/staff`, `/partners` — cada uno con sus propios Resources y auth.

## Lo mínimo

En `app/Providers/ArqelServiceProvider.php` (creado por `php artisan arqel:install`):

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

Esto registra un panel `admin` en `/admin` con 1 Resource y auth habilitado.

## API fluida

| Método | Tipo | Descripción |
|---|---|---|
| `path(string)` | `string` | Path raíz del panel (sin slash inicial) |
| `brand(string)` | `string` | Texto/título mostrado en la Topbar |
| `theme(string)` | `string` | Color primario o ID de tema personalizado |
| `primaryColor(string)` | `string` | Atajo para `--primary` |
| `darkMode(bool)` | `bool` | Habilita el toggle de dark mode en la Topbar |
| `middleware(array)` | `array` | Middleware aplicado a todas las rutas del panel |
| `resources(array)` | `array<class-string>` | Lista de clases Resource |
| `widgets(array)` | `array<class-string>` | Widgets mostrados en el dashboard |
| `navigationGroups(array)` | `array<NavigationGroup>` | Personaliza el Sidebar |
| `authGuard(string)` | `string` | Guard alternativo (por defecto `web`) |
| `tenant(class-string)` | `class-string` | Resolver de tenant (Fase 2) |

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

Cada panel es independiente — Resources, sidebar, tema y middleware separados. `arqel:install` solo crea `admin` por defecto; añade más según sea necesario.

## Cómo se resuelve el panel

`Arqel\Core\Panel\PanelRegistry` es un singleton poblado en boot. En runtime, el middleware `HandleArqelInertiaRequests` llama a `PanelRegistry::setCurrent($id)` según el path de la request, y `panel` aparece como prop compartida en `usePage().props.panel` del lado React.

```tsx
import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@arqel-dev/types';

const { panel } = usePage<SharedProps>().props;
console.log(panel.id);    // 'admin'
console.log(panel.brand); // 'Acme Admin'
console.log(panel.path);  // 'admin'
```

## Anti-patrones

- Hardcodear URLs en el front-end — usa `route('arqel.resources.index', { resource: 'users' })` (Ziggy) o los enlaces generados automáticamente por el `Sidebar`
- Compartir Resources entre panels — en su lugar, declara `Resource::canBeSeenIn(panel)` (Fase 2) o duplica la clase para diferenciar comportamiento

## Próximos pasos

- [Resources](/es/guide/resources) — declarar modelos como CRUDs
- [Auth](/es/guide/auth) — Policies + middleware del panel
- Referencia API: [PHP overview](/es/reference/php-overview)
