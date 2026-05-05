# Empezando

Esta guía construye un admin de Arqel funcional en un proyecto Laravel nuevo en **menos de 5 minutos**. Al final tendrás un CRUD de `User` con login, sidebar shadcn y páginas de auth split-screen — servido en `/admin`.

## Requisitos previos

| Herramienta | Versión | Comprobar |
|---|---|---|
| PHP | 8.3+ | `php -v` |
| Composer | 2.7+ | `composer --version` |
| Node | 20.9+ LTS | `node -v` |
| pnpm (recomendado) | 10+ | `pnpm -v` |

::: tip Versiones recomendadas
En macOS: `brew install php@8.3 composer node@22`. En Linux/WSL: usa `nvm` para Node y PHP del package manager. Habilita pnpm con `corepack enable`.
:::

## 1. Crear un proyecto Laravel

```bash
composer create-project laravel/laravel:^12.0 my-admin-app
cd my-admin-app
```

## 2. Instalar Arqel

```bash
composer require arqel-dev/arqel
php artisan arqel:install
```

`composer require arqel-dev/arqel` arrastra el stack completo vía el meta-paquete: core, auth, fields, form, actions, nav, table + `inertiajs/inertia-laravel`.

`arqel:install` hace **todo lo demás**: scaffold PHP + middleware + config de Vite + instalación de paquetes JS + páginas de auth listas para usar. Cuando se te pregunte por el package manager JS, elige `pnpm` (o `npm`/`yarn`).

::: details Flags útiles
- `--force` — sobrescribir archivos existentes sin preguntar
- `--no-frontend` — omite la instalación de paquetes npm y el scaffold de `resources/js/`/`resources/css/` (útil en CI)
:::

## 3. Setup de base de datos + primer admin

```bash
php artisan migrate
php artisan arqel:make-user
```

`arqel:make-user` pregunta interactivamente por nombre, email y contraseña. Usarás estas credenciales para iniciar sesión en el paso 5.

## 4. Iniciar los servidores de desarrollo

En **dos terminales**:

```bash
# Terminal 1 — backend Laravel
php artisan serve

# Terminal 2 — Vite dev (React HMR)
pnpm dev
```

## 5. Abrir el panel

Abre [http://localhost:8000/admin/login](http://localhost:8000/admin/login).

Verás el login de shadcn (Card split-screen con una ilustración hero a la derecha). Inicia sesión con las credenciales del paso 3 — serás redirigido a `/admin/users`, donde se monta el admin panel:

- **Sidebar izquierdo** con el brand de la app + grupo "System" + ítem destacado "Users"
- **Topbar** con toggle de sidebar (icono panel-left), brand y switcher de tema (sol/luna)
- **Tabla de Users** con paginación ("Prev / 1 / 1 / Next"), búsqueda y bulk actions

Tienes un admin panel funcional.

## Qué pasó tras bambalinas

`arqel:install` generó estos archivos:

```
my-admin-app/
├── app/
│   ├── Arqel/Resources/UserResource.php   # CRUD de User
│   ├── Http/Middleware/HandleInertiaRequests.php   # rootView=arqel.layout
│   └── Providers/ArqelServiceProvider.php  # panel admin con login
├── bootstrap/providers.php                 # provider auto-registrado
├── public/login-hero.svg                   # ilustración split-screen
├── resources/
│   ├── css/app.css                         # tokens shadcn + @source
│   ├── js/app.tsx                          # createArqelApp + páginas de auth
│   └── views/arqel/layout.blade.php        # Inertia Blade root
├── config/arqel.php                        # config publicada
└── vite.config.ts                          # React + Tailwind v4
```

Y auto-registró:
- `App\Providers\ArqelServiceProvider::class` en `bootstrap/providers.php`
- `App\Http\Middleware\HandleInertiaRequests::class` en el pipeline `web` (vía `ArqelServiceProvider::boot()`)

## Añadir tu primer Resource personalizado

Vamos a crear un Resource para `Post`. Primero el modelo + migración:

```bash
php artisan make:model Post -m
```

Edita `database/migrations/<timestamp>_create_posts_table.php`:

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('body');
    $table->boolean('published')->default(false);
    $table->timestamps();
});
```

```bash
php artisan migrate
```

Crea el Resource:

```bash
php artisan arqel:resource Post
```

Esto genera `app/Arqel/Resources/PostResource.php`. Edita el método `fields()`:

```php
namespace App\Arqel\Resources;

use App\Models\Post;
use Arqel\Core\Resources\Resource;

final class PostResource extends Resource
{
    public static string $model = Post::class;
    public static ?string $navigationIcon = 'heroicon-o-document-text';
    public static ?string $navigationGroup = 'Content';
    public static ?string $recordTitleAttribute = 'title';

    public function fields(): array
    {
        return [
            ['name' => 'title', 'type' => 'text', 'required' => true],
            ['name' => 'body', 'type' => 'textarea', 'rows' => 8],
            ['name' => 'published', 'type' => 'boolean'],
        ];
    }
}
```

Registra el Resource en `app/Providers/ArqelServiceProvider.php`:

```php
$panel->resources([
    UserResource::class,
    PostResource::class,   // ← añade aquí
]);
```

Refresca la página — el Sidebar ahora muestra el grupo "Content" con el ítem "Posts". Haz click para crear/editar/listar posts vía `/admin/posts`.

## Personalizaciones comunes

### Cambiar el branding

En `app/Providers/ArqelServiceProvider.php`:

```php
$panel
    ->path('admin')                  // cambia a 'painel' / 'cms' / etc.
    ->brand('Acme Admin')            // texto en sidebar/topbar
    ->afterLoginRedirectTo('/admin/dashboard');
```

### Añadir middleware personalizado

```php
$panel->middleware(['web', 'auth', 'verified', 'role:admin']);
```

### Cambiar el tema

La Topbar tiene un switcher light/dark/system. Para personalizar tokens (colores, radius, fuentes), edita tu propio `resources/css/app.css` y añade overrides después de `@import '@arqel-dev/ui/styles.css'`:

```css
@import '@arqel-dev/ui/styles.css';

:root {
  --primary: oklch(0.5 0.2 240);     /* ← cambia el azul primario a otro tono */
  --radius: 0.5rem;                   /* ← reduce el radius por defecto */
}
```

Más detalles en [Theming](/es/guide/theming).

## Próximos pasos

- [¿Qué es Arqel?](/es/guide/what-is-arqel) — contexto y filosofía
- [Panels](/es/guide/panels) — multi-panel, paths, middleware
- [Resources](/es/guide/resources) — fields, columnas, filtros, actions
- [Fields](/es/guide/fields) — los 21 tipos de Field
- [Tutorial: Primer CRUD](/es/guide/tutorial-first-crud) — guía más detallada

## Resolución de problemas

::: warning PHP < 8.3
Arqel requiere PHP 8.3+. `composer require arqel-dev/arqel` falla en PHP 8.2. Actualiza vía Herd, Homebrew (`brew install php@8.3`) o `phpbrew`.
:::

::: warning `Vite manifest not found`
El Vite dev server no está corriendo. En dev: `pnpm dev`. En producción: `pnpm build`.
:::

::: warning `View [app] not found`
Inertia está buscando la root view antigua (`app`). Asegúrate de que `app/Http/Middleware/HandleInertiaRequests.php` tenga `protected $rootView = 'arqel.layout';`. Si el middleware no se publicó automáticamente, `arqel:install` falló — ejecuta `php artisan arqel:install --force`.
:::

::: warning Sidebar vacío después del login
Asegúrate de tener al menos un Resource registrado en `resources([...])` en `ArqelServiceProvider`. La prop compartida `panel.navigation` se construye a partir de los Resources del panel.
:::

::: warning ENOSPC: System limit for number of file watchers reached
Vite estaba observando `vendor/` o `node_modules/`. El `vite.config.ts` publicado por la instalación ya ignora esas carpetas. Si usas una config personalizada, añade `watch.ignored`. En Linux: `echo fs.inotify.max_user_watches=524288 | sudo tee -a /etc/sysctl.conf && sudo sysctl -p`.
:::
