# Arqel Marketplace — Frontend público

App Laravel + Inertia + React (dogfood do framework Arqel) que renderiza o site público
do marketplace de plugins. Consome os models e migrations expostos por `arqel/marketplace`.

## Decisões arquiteturais

- **Dogfood Arqel**: usamos `arqel/core` para futura área `/admin/marketplace`. As páginas
  públicas (`/`, `/browse`, `/plugins/{slug}`) são Inertia "raw" — não vivem dentro de um Panel.
- **Inertia 3** é a única ponte PHP↔React (ADR-001). Sem TanStack Query / SWR.
- **Tailwind v4** via `@import 'tailwindcss';`.
- **TypeScript strict** + `noUncheckedIndexedAccess`.
- **Pest 3** para feature tests do controller (Testbench), **Vitest** para componentes React.

## Setup local

```bash
composer install --ignore-platform-req=ext-zip
corepack pnpm install
pnpm --filter @arqel/marketplace-app build
```

Após build, rode o servidor Laravel apontando para `apps/marketplace` (em produção:
docker / Forge / Cloud). As rotas estão em `routes/web.php`.

## Variáveis de ambiente

- `APP_KEY` — gerar com `php artisan key:generate`.
- `DB_CONNECTION` / credenciais — Postgres ou SQLite.
- `INERTIA_SSR_ENABLED=false` (default; sem SSR nesta slice).

## Estrutura

```
apps/marketplace/
├── app/Http/Controllers/        # LandingController, BrowseController, PluginDetailController
├── routes/web.php               # 3 rotas públicas
├── resources/
│   ├── js/
│   │   ├── Pages/Marketplace/   # Landing.tsx, Browse.tsx, PluginDetail.tsx
│   │   ├── Components/Marketplace/  # PluginCard, PluginList, CategoryFilter, ReviewList
│   │   ├── types.ts             # Plugin / PluginCategory / PluginReview types
│   │   └── app.tsx              # Inertia entry
│   ├── css/app.css              # Tailwind v4
│   └── views/app.blade.php
├── tests/Feature/               # Pest tests dos controllers
└── vite.config.ts
```

## Escopo entregue (MKTPLC-004 — slice 1)

- Landing (featured/trending/new + categorias)
- Browse (filtros type/category + paginação)
- Plugin Detail (header + tabs README/Versões/Reviews + sidebar install)

## Compare side-by-side

A rota `/compare?slugs=foo,bar,baz` renderiza uma comparação side-by-side de até 3
plugins (mínimo 2). Plugins não publicados ou inexistentes aparecem em `notFound`,
enquanto os encontrados são exibidos em `<CompareTable />` com destaque visual nas
linhas em que os valores diferem entre colunas (preço, downloads, estrelas, tipo,
versão, licença, último release, reviews).

A seleção é mantida em `localStorage` (chave `arqel:compare:slugs`, máx 3) através
do hook `useCompareSlugs`. Em `PluginCard` há o botão "Adicionar a comparar" e a
barra flutuante `<CompareFloatingBar />` (renderizada globalmente) liga para
`/compare` com os slugs selecionados.

Comportamentos:

- 422 quando a query string traz menos de 2 ou mais de 3 slugs.
- Slugs não publicados são reportados em `notFound` (sem 404).
- Ordem dos plugins na tabela respeita a ordem da query string.

## Deferido

- Publisher profile pages → `MKTPLC-004-publisher`
- Payment checkout UI (paid plugins) → `MKTPLC-004-checkout`
- SSR / Open Graph dinâmico → `MKTPLC-004-ssr`
