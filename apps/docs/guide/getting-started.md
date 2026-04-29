# Getting Started

Esse guia cria um admin Arqel funcional num projeto Laravel novo em **menos de 10 minutos**. Ao final você vai ter um CRUD de `User` com index/create/edit/show servido em `/admin`.

## Pré-requisitos

| Ferramenta | Versão | Verificar |
|---|---|---|
| PHP | 8.3+ (testado em 8.3 e 8.4) | `php -v` |
| Composer | 2.7+ | `composer --version` |
| Laravel installer | 5.x+ | `laravel --version` |
| Node | 20.9+ LTS (testado em 20 e 22) | `node -v` |
| pnpm | 10.x | `pnpm -v` |

::: tip Versões recomendadas
Use `nvm use 22` no Node e `corepack enable` para o pnpm. Em macOS, prefira PHP do Herd ou Homebrew (`brew install php@8.3`).
:::

## 1. Criar projeto Laravel

```bash
laravel new acme --pest
cd acme
```

Aceite os defaults — sem starter kit (Arqel não depende de Breeze/Jetstream).

## 2. Instalar Arqel

```bash
composer require arqel/core
php artisan arqel:install
```

`arqel:install` faz **tudo**: scaffold PHP + instalação automática dos pacotes JS + configuração de `resources/js/app.tsx` e `resources/css/app.css`. Ele detecta seu package manager (`pnpm`/`yarn`/`npm`) via lockfile e roda o equivalente:

```bash
{pm} add @arqel/react @arqel/ui @arqel/hooks @arqel/fields @arqel/types
{pm} add -D @inertiajs/react react react-dom @types/react @types/react-dom
```

Arquivos criados/modificados:

- `config/arqel.php`
- `app/Arqel/Resources/` + `app/Arqel/Widgets/`
- `app/Providers/ArqelServiceProvider.php`
- `resources/js/Pages/Arqel/`
- `resources/js/app.tsx` (com `createArqelApp`)
- `resources/css/app.css` (`@import 'tailwindcss'` + `@import '@arqel/ui/styles.css'`)
- `resources/views/arqel/layout.blade.php`
- `AGENTS.md` (contexto canónico para LLMs)

::: details Flags úteis
- `--force` — sobrescreve sem prompt
- `--no-frontend` — pula instalação de pacotes JS e scaffold de `resources/js/`/`resources/css/` (útil em CI smoke tests ou contribuidores do monorepo)
:::

::: tip Já tem um `app.tsx` configurado?
O comando é idempotente: se `resources/js/app.tsx` já contém `import '@arqel/ui/styles.css'`, ele pula esse step. Mesma coisa pro `app.css`. Use `--force` para forçar rescrita.
:::

## 3. Gerar primeiro Resource

Laravel já trás o model `User` por defeito. Vamos servi-lo no admin:

```bash
php artisan arqel:resource User --with-policy
```

Isso cria:

- `app/Arqel/Resources/UserResource.php` — declara fields/table/actions
- `app/Policies/UserPolicy.php` — gate de auth (vazia por defeito; allow-all até você editar)

Edite `UserResource::fields()` para algo como:

```php
namespace App\Arqel\Resources;

use App\Models\User;
use Arqel\Core\Resources\Resource;
use Arqel\Fields\FieldFactory as Field;

final class UserResource extends Resource
{
    protected static string $model = User::class;
    protected static ?string $navigationIcon = 'users';

    public function fields(): array
    {
        return [
            Field::text('name')->required()->maxLength(120),
            Field::email('email')->required()->unique(User::class, 'email'),
            Field::password('password')->required()->minLength(8),
        ];
    }
}
```

## 4. Subir o servidor

Em terminais separados:

```bash
php artisan serve
pnpm dev
```

Abra <http://127.0.0.1:8000/admin/users>. Você deve ver o `UserResource` listado, com index, "Create user" no toolbar e ações row de edit/delete.

## 5. (Opcional) Login

Arqel não força um starter kit de auth — use o que preferir (Breeze, Jetstream, Fortify). Em dev, autentique manualmente via `tinker`:

```bash
php artisan tinker
> User::factory()->create(['email' => 'admin@example.com'])
```

E adicione um middleware `auth` no panel, em `config/arqel.php`:

```php
'panel' => [
    'middleware' => ['web', 'auth'],
],
```

## Próximos passos

- [O que é Arqel?](/guide/what-is-arqel) — visão geral e contexto
- [Conceitos: Panels, Resources, Fields](/guide/panels) — entender as primitivas
- [Custom Fields](/advanced/custom-fields) — criar inputs próprios
- [Exemplo: Blog admin](/examples/blog-admin) — tutorial passo-a-passo

## Troubleshooting

::: warning PHP < 8.3
Arqel **requer PHP 8.3+**. `composer require arqel/core` em PHP 8.2 falha com mensagem de versão. Atualize via Herd, Homebrew, ou `phpbrew`.
:::

::: warning Node < 20.9
A toolchain Vite/tsup do Arqel exige Node 20.9+. `pnpm install` em Node 18 falha com erros de `node:fs/promises`. Use `nvm install 22 && nvm use 22`.
:::

::: warning Permissions em `storage/`
Erro 500 com "Permission denied" em `storage/logs/laravel.log` é Laravel-clássico, não Arqel. Rode `chmod -R 775 storage bootstrap/cache && chown -R $USER:www-data storage`.
:::

::: warning `Class "Arqel\Fields\FieldFactory" not found`
O ServiceProvider de `arqel/fields` é auto-discovered, mas se você desabilitou auto-discovery em `composer.json` (`extra.laravel.dont-discover`), registe `Arqel\Fields\FieldServiceProvider::class` manualmente em `bootstrap/providers.php`.
:::
