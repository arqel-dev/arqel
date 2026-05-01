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

## Checkout flow (MKTPLC-004-checkout)

Fluxo completo de compra de plugins premium dentro do site público — usa `arqel/marketplace`
`PaymentGateway` (Mock/Stripe via DI) e `LicenseKeyGenerator`.

Rotas (todas em `web,auth`):

- `GET  /checkout/{slug}` — página de resumo (`Marketplace/Checkout`) com preço, taxa estimada
  da plataforma e total. 422 se plugin é free ou se o usuário já comprou.
- `POST /checkout/{slug}/initiate` — cria/atualiza `PluginPurchase` em `pending` e faz redirect
  externo para a `url` da `CheckoutSession` retornada pelo gateway.
- `GET  /checkout/{slug}/success?session_id=...` — verifica pagamento, gera license key
  `ARQ-XXXX-XXXX-XXXX-XXXX`, dispara `PluginPurchased`, e renderiza `Marketplace/CheckoutSuccess`
  com botão de copiar a chave + link de download.
- `GET  /checkout/{slug}/cancel` — `Marketplace/CheckoutCancelled` com botão "Tentar novamente".

Na página `/plugins/{slug}` aparece um botão "Comprar agora" quando o plugin é premium e o
usuário ainda não comprou. Quando já é dono, aparece um badge "Você já tem esse plugin".

## Deferido

- Compare side-by-side de plugins → `MKTPLC-004-compare`
- Publisher profile pages → `MKTPLC-004-publisher`
- SSR / Open Graph dinâmico → `MKTPLC-004-ssr`
