# Instalação

Para o walkthrough completo (com configuração de `app.tsx` e geração de Resource), veja [Getting Started](/guide/getting-started). Esse capítulo lista apenas as instruções de instalação dos pacotes.

## Composer (PHP)

```bash
composer require arqel-dev/core
php artisan arqel:install
```

`arqel:install` faz scaffold interativo via Laravel Prompts. Use `--force` para sobrescrever sem prompt.

Para puxar pacotes adicionais:

```bash
composer require arqel-dev/fields arqel-dev/table arqel-dev/form arqel-dev/actions arqel-dev/auth arqel-dev/nav
```

> **Nota:** os pacotes PHP têm auto-discovery via Laravel Service Providers. Se você desabilitou auto-discovery (`composer.json` → `extra.laravel.dont-discover`), registre os providers manualmente em `bootstrap/providers.php`.

## pnpm (JavaScript)

```bash
pnpm add @arqel-dev/react @arqel-dev/ui @arqel-dev/hooks @arqel-dev/fields @arqel-dev/types
pnpm add -D @inertiajs/react react react-dom @types/react @types/react-dom
```

Versões mínimas:

- React 19.2+
- @inertiajs/react 2+
- TypeScript 5.6+ (`strict: true`, `noUncheckedIndexedAccess: true`)
- Node 20.9+ LTS

## Tailwind v4

Em `resources/css/app.css`:

```css
@import 'tailwindcss';
@import '@arqel-dev/ui/styles.css';
```

Arqel usa **Tailwind v4 syntax** (`@import` em vez de `@tailwind base`). Se você está em v3, atualize via:

```bash
pnpm add -D tailwindcss@^4
```

## Composer path repositories (dev local do monorepo)

Se você está contribuindo no monorepo Arqel:

```json
"repositories": [
    { "type": "path", "url": "packages/core" },
    { "type": "path", "url": "packages/fields" }
]
```

`pnpm install` usa workspaces (`pnpm-workspace.yaml`) automaticamente.

## Próximos passos

- [Getting Started](/guide/getting-started) — walkthrough completo
- [Panels](/guide/panels) — declarar primeiro panel
