# Getting Started

Este guia cria um admin Arqel funcional num projecto Laravel novo em **menos de 5 minutos**. No fim vais ter um CRUD de `User` com login, sidebar shadcn e split-screen auth pages — servido em `/admin`.

## Pré-requisitos

| Ferramenta | Versão | Verificar |
|---|---|---|
| PHP | 8.3+ | `php -v` |
| Composer | 2.7+ | `composer --version` |
| Node | 20.9+ LTS | `node -v` |
| pnpm (recomendado) | 10+ | `pnpm -v` |

::: tip Versões recomendadas
Em macOS: `brew install php@8.3 composer node@22`. Em Linux/WSL: usa `nvm` para o Node e o PHP do teu package manager. Habilita o pnpm com `corepack enable`.
:::

## 1. Criar projecto Laravel

```bash
composer create-project laravel/laravel:^12.0 my-admin-app
cd my-admin-app
```

## 2. Instalar Arqel

```bash
composer require arqel-dev/framework
php artisan arqel:install
```

O `composer require arqel-dev/framework` puxa o stack completo via meta-package: core, auth, fields, form, actions, nav, table + `inertiajs/inertia-laravel`.

O `arqel:install` faz **tudo o resto**: scaffold PHP + middleware + Vite config + instalação dos pacotes JS + auth pages prontas. Quando perguntar pelo gestor de pacotes JS, escolhe `pnpm` (ou `npm`/`yarn`).

::: details Flags úteis
- `--force` — sobrescreve ficheiros existentes sem prompt
- `--no-frontend` — pula instalação dos pacotes npm e scaffold de `resources/js/`/`resources/css/` (útil em CI)
:::

## 3. Setup da base de dados + primeiro admin

```bash
php artisan migrate
php artisan arqel:make-user
```

`arqel:make-user` faz prompt interactivo para nome, email e password. Vais usar estas credenciais para fazer login no passo 5.

## 4. Arrancar os dev servers

Em **dois terminais**:

```bash
# Terminal 1 — backend Laravel
php artisan serve

# Terminal 2 — Vite dev (HMR React)
pnpm dev
```

## 5. Abrir o painel

Abre [http://localhost:8000/admin/login](http://localhost:8000/admin/login).

Vês o login shadcn (Card split-screen com hero illustration à direita). Faz login com as credenciais do passo 3 — sé redireccionado para `/admin/users`, onde o painel admin está montado:

- **Sidebar à esquerda** com brand do app + grupo "System" + item "Users" highlighted
- **Topbar** com toggle da sidebar (icon panel-left), brand e theme switcher (sol/lua)
- **Tabela de Users** com paginação ("Prev / 1 / 1 / Next"), search e bulk actions

🎉 Tens um painel admin a funcionar.

## O que aconteceu nos bastidores

O `arqel:install` gerou estes ficheiros:

```
my-admin-app/
├── app/
│   ├── Arqel/Resources/UserResource.php   # CRUD de User
│   ├── Http/Middleware/HandleInertiaRequests.php   # rootView=arqel.layout
│   └── Providers/ArqelServiceProvider.php  # panel admin com login
├── bootstrap/providers.php                 # provider auto-registrado
├── public/login-hero.svg                   # ilustração split-screen
├── resources/
│   ├── css/app.css                         # shadcn tokens + @source
│   ├── js/app.tsx                          # createArqelApp + auth pages
│   └── views/arqel/layout.blade.php        # Inertia Blade root
├── config/arqel.php                        # config publicada
└── vite.config.ts                          # React + Tailwind v4
```

E auto-registou:
- `App\Providers\ArqelServiceProvider::class` em `bootstrap/providers.php`
- `App\Http\Middleware\HandleInertiaRequests::class` no pipeline `web` (via `ArqelServiceProvider::boot()`)

## Adicionar o teu primeiro Resource customizado

Vamos criar um Resource para `Post`. Primeiro, o model + migration:

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

Cria o Resource:

```bash
php artisan arqel:resource Post
```

Isso gera `app/Arqel/Resources/PostResource.php`. Edita o método `fields()`:

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

Regista o Resource no `app/Providers/ArqelServiceProvider.php`:

```php
$panel->resources([
    UserResource::class,
    PostResource::class,   // ← adiciona aqui
]);
```

Refresca a página — agora a Sidebar mostra o grupo "Content" com o item "Posts". Click cria/edita/lista posts via `/admin/posts`.

## Customizações comuns

### Mudar branding

Em `app/Providers/ArqelServiceProvider.php`:

```php
$panel
    ->path('admin')                  // muda para 'painel' / 'cms' / etc.
    ->brand('Acme Admin')            // texto na sidebar/topbar
    ->afterLoginRedirectTo('/admin/dashboard');
```

### Adicionar middleware customizado

```php
$panel->middleware(['web', 'auth', 'verified', 'role:admin']);
```

### Mudar o tema

O Topbar tem um switcher light/dark/system. Para customizar tokens (cores, raio, fonts), edita o teu próprio `resources/css/app.css` adicionando overrides depois do `@import '@arqel-dev/ui/styles.css'`:

```css
@import '@arqel-dev/ui/styles.css';

:root {
  --primary: oklch(0.5 0.2 240);     /* ← muda o azul primary para outro tom */
  --radius: 0.5rem;                   /* ← reduz o radius default */
}
```

Mais detalhes em [Theming](/pt-BR/guide/theming).

## Próximos passos

- [O que é Arqel?](/pt-BR/guide/what-is-arqel) — contexto e filosofia
- [Panels](/pt-BR/guide/panels) — multi-panel, paths, middleware
- [Resources](/pt-BR/guide/resources) — fields, columns, filters, actions
- [Fields](/pt-BR/guide/fields) — todos os 21 tipos de field
- [Tutorial: Primeiro CRUD](/pt-BR/guide/tutorial-first-crud) — guia mais detalhado
- [MCP server](/pt-BR/guide/mcp-server) — dá ao Claude Code, Cursor e outros assistentes de IA acesso project-aware aos docs do Arqel e ao scaffolding

## Troubleshooting

::: warning PHP < 8.3
Arqel requer PHP 8.3+. `composer require arqel-dev/framework` em PHP 8.2 falha. Atualiza via Herd, Homebrew (`brew install php@8.3`), ou `phpbrew`.
:::

::: warning `Vite manifest not found`
O Vite dev server não está a correr. Em dev: `pnpm dev`. Em produção: `pnpm build`.
:::

::: warning `View [app] not found`
O Inertia procura o root view antigo (`app`). Confirma que `app/Http/Middleware/HandleInertiaRequests.php` tem `protected $rootView = 'arqel.layout';`. Se o middleware não foi auto-publicado, o `arqel:install` falhou — corre `php artisan arqel:install --force`.
:::

::: warning Sidebar vazia depois do login
Confirma que tens pelo menos um Resource registado em `resources([...])` no `ArqelServiceProvider`. O `panel.navigation` shared prop é construído a partir dos Resources do panel.
:::

::: warning ENOSPC: System limit for number of file watchers reached
O Vite estava a vigiar `vendor/` ou `node_modules/`. O `vite.config.ts` publicado pelo install já ignora estas pastas. Se estiveres a usar um config customizado, adiciona o `watch.ignored`. Em Linux: `echo fs.inotify.max_user_watches=524288 | sudo tee -a /etc/sysctl.conf && sudo sysctl -p`.
:::
