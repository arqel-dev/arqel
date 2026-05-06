# Getting Started

This guide builds a working Arqel admin in a fresh Laravel project in **under 5 minutes**. By the end you'll have a `User` CRUD with login, shadcn sidebar, and split-screen auth pages — served at `/admin`.

## Prerequisites

| Tool | Version | Check |
|---|---|---|
| PHP | 8.3+ | `php -v` |
| Composer | 2.7+ | `composer --version` |
| Node | 20.9+ LTS | `node -v` |
| pnpm (recommended) | 10+ | `pnpm -v` |

::: tip Recommended versions
On macOS: `brew install php@8.3 composer node@22`. On Linux/WSL: use `nvm` for Node and PHP from your package manager. Enable pnpm with `corepack enable`.
:::

## 1. Create a Laravel project

```bash
composer create-project laravel/laravel:^12.0 my-admin-app
cd my-admin-app
```

## 2. Install Arqel

```bash
composer require arqel-dev/framework
php artisan arqel:install
```

`composer require arqel-dev/framework` pulls the full stack via the meta-package: core, auth, fields, form, actions, nav, table + `inertiajs/inertia-laravel`.

`arqel:install` does **everything else**: PHP scaffold + middleware + Vite config + JS package installation + ready-to-use auth pages. When prompted for the JS package manager, pick `pnpm` (or `npm`/`yarn`).

::: details Useful flags
- `--force` — overwrite existing files without prompting
- `--no-frontend` — skip npm package installation and `resources/js/`/`resources/css/` scaffold (useful in CI)
:::

## 3. Database setup + first admin

```bash
php artisan migrate
php artisan arqel:make-user
```

`arqel:make-user` interactively prompts for name, email, and password. You'll use these credentials to log in at step 5.

## 4. Start the dev servers

In **two terminals**:

```bash
# Terminal 1 — Laravel backend
php artisan serve

# Terminal 2 — Vite dev (React HMR)
pnpm dev
```

## 5. Open the panel

Open [http://localhost:8000/admin/login](http://localhost:8000/admin/login).

You'll see the shadcn login (split-screen Card with a hero illustration on the right). Log in with the credentials from step 3 — you're redirected to `/admin/users`, where the admin panel is mounted:

- **Left sidebar** with the app brand + "System" group + highlighted "Users" item
- **Topbar** with sidebar toggle (panel-left icon), brand, and theme switcher (sun/moon)
- **Users table** with pagination ("Prev / 1 / 1 / Next"), search, and bulk actions

You have a working admin panel.

## What happened behind the scenes

`arqel:install` generated these files:

```
my-admin-app/
├── app/
│   ├── Arqel/Resources/UserResource.php   # User CRUD
│   ├── Http/Middleware/HandleInertiaRequests.php   # rootView=arqel.layout
│   └── Providers/ArqelServiceProvider.php  # admin panel with login
├── bootstrap/providers.php                 # provider auto-registered
├── public/login-hero.svg                   # split-screen illustration
├── resources/
│   ├── css/app.css                         # shadcn tokens + @source
│   ├── js/app.tsx                          # createArqelApp + auth pages
│   └── views/arqel/layout.blade.php        # Inertia Blade root
├── config/arqel.php                        # published config
└── vite.config.ts                          # React + Tailwind v4
```

And it auto-registered:
- `App\Providers\ArqelServiceProvider::class` in `bootstrap/providers.php`
- `App\Http\Middleware\HandleInertiaRequests::class` in the `web` pipeline (via `ArqelServiceProvider::boot()`)

## Add your first custom Resource

Let's create a Resource for `Post`. First the model + migration:

```bash
php artisan make:model Post -m
```

Edit `database/migrations/<timestamp>_create_posts_table.php`:

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

Create the Resource:

```bash
php artisan arqel:resource Post
```

This generates `app/Arqel/Resources/PostResource.php`. Edit the `fields()` method:

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

Register the Resource in `app/Providers/ArqelServiceProvider.php`:

```php
$panel->resources([
    UserResource::class,
    PostResource::class,   // ← add here
]);
```

Refresh the page — the Sidebar now shows the "Content" group with the "Posts" item. Click to create/edit/list posts via `/admin/posts`.

## Common customizations

### Change branding

In `app/Providers/ArqelServiceProvider.php`:

```php
$panel
    ->path('admin')                  // change to 'painel' / 'cms' / etc.
    ->brand('Acme Admin')            // text in sidebar/topbar
    ->afterLoginRedirectTo('/admin/dashboard');
```

### Add custom middleware

```php
$panel->middleware(['web', 'auth', 'verified', 'role:admin']);
```

### Change the theme

The Topbar has a light/dark/system switcher. To customize tokens (colors, radius, fonts), edit your own `resources/css/app.css` and add overrides after `@import '@arqel-dev/ui/styles.css'`:

```css
@import '@arqel-dev/ui/styles.css';

:root {
  --primary: oklch(0.5 0.2 240);     /* ← change the primary blue to a different tone */
  --radius: 0.5rem;                   /* ← reduce the default radius */
}
```

More details in [Theming](/guide/theming).

## Next steps

- [What is Arqel?](/guide/what-is-arqel) — context and philosophy
- [Panels](/guide/panels) — multi-panel, paths, middleware
- [Resources](/guide/resources) — fields, columns, filters, actions
- [Fields](/guide/fields) — all 21 field types
- [Tutorial: First CRUD](/guide/tutorial-first-crud) — more detailed guide
- [MCP server](/guide/mcp-server) — give Claude Code, Cursor, and other AI assistants project-aware access to Arqel docs and scaffolding

## Troubleshooting

::: warning PHP < 8.3
Arqel requires PHP 8.3+. `composer require arqel-dev/framework` fails on PHP 8.2. Upgrade via Herd, Homebrew (`brew install php@8.3`), or `phpbrew`.
:::

::: warning `Vite manifest not found`
The Vite dev server is not running. In dev: `pnpm dev`. In production: `pnpm build`.
:::

::: warning `View [app] not found`
Inertia is looking for the old root view (`app`). Make sure `app/Http/Middleware/HandleInertiaRequests.php` has `protected $rootView = 'arqel.layout';`. If the middleware was not auto-published, `arqel:install` failed — run `php artisan arqel:install --force`.
:::

::: warning Empty sidebar after login
Make sure you have at least one Resource registered in `resources([...])` in `ArqelServiceProvider`. The `panel.navigation` shared prop is built from the panel's Resources.
:::

::: warning ENOSPC: System limit for number of file watchers reached
Vite was watching `vendor/` or `node_modules/`. The `vite.config.ts` published by the install already ignores those folders. If you're using a custom config, add `watch.ignored`. On Linux: `echo fs.inotify.max_user_watches=524288 | sudo tee -a /etc/sysctl.conf && sudo sysctl -p`.
:::
