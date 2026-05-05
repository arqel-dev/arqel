# Instalação

Este guia cobre a instalação do Arqel num app Laravel **fresh** ou existente. Para o walkthrough end-to-end (incluindo o teu primeiro Resource e UI), continua para [Getting Started](/pt-BR/guide/getting-started).

## Pré-requisitos

| | Versão mínima |
|---|---|
| **PHP** | 8.3 |
| **Composer** | 2.x |
| **Laravel** | 12 |
| **Node** | 20.9 LTS |
| **pnpm** (recomendado) | 10+ |

`npm` e `yarn` também funcionam — o `arqel:install` detecta automaticamente o gestor pelo lockfile.

## Passo 1 — Cria um Laravel 12

Se ainda não tens uma app:

```bash
composer create-project laravel/laravel:^12.0 my-admin-app
cd my-admin-app
```

Se já tens uma app Laravel 12 a correr, salta este passo.

## Passo 2 — Instala o meta-package

```bash
composer require arqel-dev/framework
```

O meta-package `arqel-dev/framework` puxa o stack inteiro do Arqel + `inertiajs/inertia-laravel`:

- `arqel-dev/core` — panels, resources, rotas polimórficas, Inertia bridge, command palette, telemetry
- `arqel-dev/auth` — login / register / forgot / reset / verify-email bundled
- `arqel-dev/fields` — schema types
- `arqel-dev/form` — render server-side
- `arqel-dev/actions` — contratos + invokers
- `arqel-dev/nav` — navigation builder
- `arqel-dev/table` — query / sort / filter / paginate
- `inertiajs/inertia-laravel` — peer obrigatório

## Passo 3 — Corre o instalador

```bash
php artisan arqel:install
```

Quando perguntar pelo gestor de pacotes JS, escolhe **`pnpm`**, **`npm`** ou **`yarn`**. O instalador:

1. Publica `config/arqel.php`
2. Gera `app/Providers/ArqelServiceProvider.php` com o panel `admin` configurado (login + registration + UserResource)
3. **Auto-regista** o provider em `bootstrap/providers.php` (idempotente)
4. Gera `app/Arqel/Resources/UserResource.php` (CRUD de users como exemplo)
5. Publica `app/Http/Middleware/HandleInertiaRequests.php` com `rootView = 'arqel.layout'`
6. **Auto-regista** o middleware no pipeline `web` via `ArqelServiceProvider::boot()` (sem editar `bootstrap/app.php`)
7. Gera `resources/views/arqel/layout.blade.php` (Blade root sem dependência do Ziggy `@routes`)
8. Gera `resources/js/app.tsx` (createArqelApp + AppShell + auth pages)
9. Gera `resources/css/app.css` (shadcn tokens via `@import '@arqel-dev/ui/styles.css'` + `@source` para os pacotes do framework)
10. Publica `vite.config.ts` (substitui o `vite.config.js` default — React + Tailwind v4)
11. Publica `public/login-hero.svg` (ilustração do split-screen de auth)
12. Gera `AGENTS.md` (instruções para Claude Code / Cursor / outros LLMs)
13. Instala via `pnpm/npm/yarn` os pacotes JS: `@arqel-dev/{react,ui,auth,hooks,fields,types}` + peers (React, Inertia, Tailwind, Vite, plugins)

## Passo 4 — Setup da base de dados + primeiro admin

```bash
php artisan migrate
php artisan arqel:make-user
```

`arqel:make-user` faz prompt interactivo para `name`, `email` e `password`. Para uso non-interactive (ex: CI):

```bash
php artisan arqel:make-user --name="Ada" --email="ada@example.com" --password="secret"
```

## Passo 5 — Arranca os dev servers

Em **dois terminais**:

```bash
# Terminal 1 — backend
php artisan serve

# Terminal 2 — Vite dev (HMR)
pnpm dev   # ou: npm run dev / yarn dev
```

Abre [http://localhost:8000/admin/login](http://localhost:8000/admin/login) e faz login com as credenciais do passo 4.

## Estrutura final

Depois do install, o teu app tem esta forma:

```
my-admin-app/
├── app/
│   ├── Arqel/
│   │   └── Resources/
│   │       └── UserResource.php       # gerado pelo install
│   ├── Http/
│   │   └── Middleware/
│   │       └── HandleInertiaRequests.php   # gerado, rootView=arqel.layout
│   └── Providers/
│       └── ArqelServiceProvider.php   # gerado, regista o panel admin
├── bootstrap/
│   └── providers.php                  # ArqelServiceProvider auto-registrado
├── config/
│   └── arqel.php                      # config publicada
├── public/
│   └── login-hero.svg                 # ilustração split-screen
├── resources/
│   ├── css/app.css                    # @import shadcn + @source workspace
│   ├── js/app.tsx                     # createArqelApp + auth + arqelPages
│   └── views/arqel/layout.blade.php   # Blade root Inertia
└── vite.config.ts                     # React + Tailwind + watch ignored
```

## Customizações comuns

### Mudar o brand

`bootstrap/providers.php` carrega `App\Providers\ArqelServiceProvider`. Edita `app/Providers/ArqelServiceProvider.php` para mudar o brand, path do panel ou login redirect:

```php
$panel = $registry
    ->panel('admin')
    ->path('admin')                           // muda para 'painel' se preferires
    ->brand('Acme Admin')                     // texto/logo no topo da Sidebar
    ->login()
    ->afterLoginRedirectTo('/admin/users')    // landing após login
    ->registration()
    ->resources([
        UserResource::class,
        // adiciona mais Resources aqui
    ]);
```

### Adicionar mais Resources

```bash
php artisan make:model Post -m
php artisan arqel:resource Post   # gera app/Arqel/Resources/PostResource.php
```

E adiciona à lista `resources([...])` no provider.

### Mudar o tema (light/dark/system)

O `ThemeProvider` (de `@arqel-dev/react`) lê `localStorage.arqel-theme`. O switcher já está no Topbar. Para customizar tokens, edita as CSS vars `--primary`, `--background`, etc. — ver [Theming](/pt-BR/guide/theming).

## Resolução de problemas

### `Vite manifest not found`

O Vite dev server não está a correr. Em local dev: `pnpm dev`. Em produção: `pnpm build`.

### `View [app] not found`

Significa que o Inertia está a procurar o root view antigo. Confirma que `app/Http/Middleware/HandleInertiaRequests.php` tem `protected $rootView = 'arqel.layout';` e que o middleware está no pipeline `web` (verifica `php artisan route:list --path=admin/login` mostra middleware `web`).

### Sidebar vazia depois de login

Confirma que tens pelo menos um Resource registado em `resources([...])` no `ArqelServiceProvider`. O `panel.navigation` é construído automaticamente a partir dos Resources do panel.

### Login retorna 404 depois de submeter

Confirma que o panel tem `->afterLoginRedirectTo('/admin/<resource-slug>')` para uma rota que existe. Por default, o stub aponta para `/admin/users` (que existe via `UserResource`).

## Próximos passos

- [Getting Started](/pt-BR/guide/getting-started) — walkthrough completo do primeiro CRUD
- [Panels](/pt-BR/guide/panels) — configurar paths, branding, middleware, multi-panel
- [Resources](/pt-BR/guide/resources) — declarar fields, columns, filters, actions
- [Authentication](/pt-BR/guide/authentication) — opções de login/register e flow customizado
- [Theming](/pt-BR/guide/theming) — tokens shadcn, dark mode, customização visual
