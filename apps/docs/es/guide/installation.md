# Instalación

Esta guía cubre la instalación de Arqel en una app Laravel **nueva** o existente. Para la guía paso a paso end-to-end (incluyendo tu primer Resource y UI), continúa en [Empezando](/es/guide/getting-started).

## Requisitos previos

| | Versión mínima |
|---|---|
| **PHP** | 8.3 |
| **Composer** | 2.x |
| **Laravel** | 12 |
| **Node** | 20.9 LTS |
| **pnpm** (recomendado) | 10+ |

`npm` y `yarn` también funcionan — `arqel:install` detecta automáticamente el package manager a partir del lockfile.

## Paso 1 — Crear una app Laravel 12

Si aún no tienes una app:

```bash
composer create-project laravel/laravel:^12.0 my-admin-app
cd my-admin-app
```

Si ya tienes una app Laravel 12 en marcha, salta este paso.

## Paso 2 — Instalar el meta-paquete

```bash
composer require arqel-dev/framework
```

El meta-paquete `arqel-dev/framework` arrastra todo el stack de Arqel + `inertiajs/inertia-laravel`:

- `arqel-dev/core` — panels, resources, rutas polimórficas, bridge de Inertia, command palette, telemetría
- `arqel-dev/auth` — login / register / forgot / reset / verify-email incluido
- `arqel-dev/fields` — tipos de schema
- `arqel-dev/form` — render del lado del servidor
- `arqel-dev/actions` — contratos + invokers
- `arqel-dev/nav` — navigation builder
- `arqel-dev/table` — query / sort / filter / paginate
- `inertiajs/inertia-laravel` — peer requerido

## Paso 3 — Ejecutar el instalador

```bash
php artisan arqel:install
```

Cuando se te pregunte por el package manager JS, elige **`pnpm`**, **`npm`** o **`yarn`**. El instalador:

1. Publica `config/arqel.php`
2. Genera `app/Providers/ArqelServiceProvider.php` con el panel `admin` configurado (login + registro + UserResource)
3. **Auto-registra** el provider en `bootstrap/providers.php` (idempotente)
4. Genera `app/Arqel/Resources/UserResource.php` (CRUD de User como ejemplo)
5. Publica `app/Http/Middleware/HandleInertiaRequests.php` con `rootView = 'arqel.layout'`
6. **Auto-registra** el middleware en el pipeline `web` vía `ArqelServiceProvider::boot()` (sin editar `bootstrap/app.php`)
7. Genera `resources/views/arqel/layout.blade.php` (Blade root sin dependencia de `@routes` de Ziggy)
8. Genera `resources/js/app.tsx` (createArqelApp + AppShell + páginas de auth)
9. Genera `resources/css/app.css` (tokens shadcn vía `@import '@arqel-dev/ui/styles.css'` + `@source` para los paquetes del framework)
10. Publica `vite.config.ts` (reemplaza el `vite.config.js` por defecto — React + Tailwind v4)
11. Publica `public/login-hero.svg` (ilustración de auth split-screen)
12. Genera `AGENTS.md` (instrucciones para Claude Code / Cursor / otros LLMs)
13. Instala los paquetes JS vía `pnpm/npm/yarn`: `@arqel-dev/{react,ui,auth,hooks,fields,types}` + peers (React, Inertia, Tailwind, Vite, plugins)

## Paso 4 — Setup de base de datos + primer admin

```bash
php artisan migrate
php artisan arqel:make-user
```

`arqel:make-user` pregunta interactivamente por `name`, `email` y `password`. Para uso no interactivo (e.g. CI):

```bash
php artisan arqel:make-user --name="Ada" --email="ada@example.com" --password="secret"
```

## Paso 5 — Iniciar los servidores de desarrollo

En **dos terminales**:

```bash
# Terminal 1 — backend
php artisan serve

# Terminal 2 — Vite dev (HMR)
pnpm dev   # o: npm run dev / yarn dev
```

Abre [http://localhost:8000/admin/login](http://localhost:8000/admin/login) e inicia sesión con las credenciales del paso 4.

## Estructura final

Después de instalar, tu app queda así:

```
my-admin-app/
├── app/
│   ├── Arqel/
│   │   └── Resources/
│   │       └── UserResource.php       # generado por install
│   ├── Http/
│   │   └── Middleware/
│   │       └── HandleInertiaRequests.php   # generado, rootView=arqel.layout
│   └── Providers/
│       └── ArqelServiceProvider.php   # generado, registra el panel admin
├── bootstrap/
│   └── providers.php                  # ArqelServiceProvider auto-registrado
├── config/
│   └── arqel.php                      # config publicada
├── public/
│   └── login-hero.svg                 # ilustración split-screen
├── resources/
│   ├── css/app.css                    # @import shadcn + @source workspace
│   ├── js/app.tsx                     # createArqelApp + auth + arqelPages
│   └── views/arqel/layout.blade.php   # Inertia Blade root
└── vite.config.ts                     # React + Tailwind + watch ignorado
```

## Personalizaciones comunes

### Cambiar el brand

`bootstrap/providers.php` carga `App\Providers\ArqelServiceProvider`. Edita `app/Providers/ArqelServiceProvider.php` para cambiar el brand, el path del panel o el redirect de login:

```php
$panel = $registry
    ->panel('admin')
    ->path('admin')                           // cambia a 'painel' si lo prefieres
    ->brand('Acme Admin')                     // texto/logo en la parte superior del Sidebar
    ->login()
    ->afterLoginRedirectTo('/admin/users')    // landing tras el login
    ->registration()
    ->resources([
        UserResource::class,
        // añade más Resources aquí
    ]);
```

### Añadir más Resources

```bash
php artisan make:model Post -m
php artisan arqel:resource Post   # genera app/Arqel/Resources/PostResource.php
```

Luego añádelo a la lista `resources([...])` en el provider.

### Cambiar el tema (light/dark/system)

El `ThemeProvider` (de `@arqel-dev/react`) lee `localStorage.arqel-theme`. El switcher ya está en la Topbar. Para personalizar tokens, edita las CSS vars `--primary`, `--background`, etc. — ver [Theming](/es/guide/theming).

## Resolución de problemas

### `Vite manifest not found`

El Vite dev server no está corriendo. En dev local: `pnpm dev`. En producción: `pnpm build`.

### `View [app] not found`

Inertia está buscando la root view antigua. Asegúrate de que `app/Http/Middleware/HandleInertiaRequests.php` tenga `protected $rootView = 'arqel.layout';` y que el middleware esté en el pipeline `web` (verifica con `php artisan route:list --path=admin/login` mostrando middleware `web`).

### Sidebar vacío después del login

Asegúrate de tener al menos un Resource registrado en `resources([...])` en `ArqelServiceProvider`. La `panel.navigation` se construye automáticamente a partir de los Resources del panel.

### Login devuelve 404 tras submit

Asegúrate de que el panel tenga `->afterLoginRedirectTo('/admin/<resource-slug>')` apuntando a una ruta que exista. Por defecto el stub apunta a `/admin/users` (que existe vía `UserResource`).

## Próximos pasos

- [Empezando](/es/guide/getting-started) — guía paso a paso completa de tu primer CRUD
- [Panels](/es/guide/panels) — configurar paths, branding, middleware, multi-panel
- [Resources](/es/guide/resources) — declarar fields, columnas, filtros, actions
- [Authentication](/es/guide/authentication) — opciones de login/registro y flujos personalizados
- [Theming](/es/guide/theming) — tokens shadcn, dark mode, personalización visual
