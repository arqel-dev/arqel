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

## Publisher profiles (MKTPLC-004-publisher)

Cada publisher tem uma página pública em `/publishers/{slug}` renderizada pelo
`PublisherProfileController`. A página agrega:

- Header com avatar (ou fallback de iniciais), nome, badge "Verificado" e bio.
- Links sociais (`website_url`, `github_url`, `twitter_handle`) — renderizados apenas
  quando preenchidos.
- 3 KPIs: total de plugins published, total de downloads (instalações) e rating médio
  (de reviews `published`).
- Grid `<PluginList />` com os plugins published do publisher, ordenados por
  `created_at` desc. Empty state em PT-BR quando o publisher ainda não tem plugins.

O `<PublisherBadge />` (avatar + nome + checkmark verificado) é reutilizado dentro
do `<PluginCard />` quando o plugin tem `publisher` snapshot anexado.

Schema novo: tabela `arqel_publishers` (ver `packages/marketplace/database/migrations/2026_05_08_000000_create_arqel_publishers.php`)
e coluna `arqel_plugins.publisher_id` (FK lógica nullable).

## Deferido

- Compare side-by-side de plugins → `MKTPLC-004-compare`
- Payment checkout UI (paid plugins) → `MKTPLC-004-checkout`
- SSR / Open Graph dinâmico → `MKTPLC-004-ssr`
