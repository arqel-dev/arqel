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

## Compare side-by-side (MKTPLC-004-compare)

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

## SEO + Open Graph (MKTPLC-004-ssr)

Não usamos full Inertia SSR (`@inertiajs/server`) — o overhead de Node service em
produção não compensa para um site público de poucas páginas. Em vez disso, fazemos
"partial SSR" focado em meta tags:

- **Server-side via Blade** — `resources/views/app.blade.php` consome o value object
  `App\Support\SeoData` (compartilhado por `View::share('seo', ...)` em cada controller)
  para emitir `<title>`, `<meta name="description">`, Open Graph (`og:title`,
  `og:description`, `og:image`, `og:type`), Twitter Card (`twitter:card`,
  `twitter:title`, `twitter:description`, `twitter:image`) e JSON-LD Schema.org.
  Crawlers (GoogleBot, BingBot) e social shares (Twitter/X, Slack, Discord, LinkedIn)
  recebem HTML totalmente populado no primeiro byte.
- **Client-side via `<MetaTags />`** — `resources/js/Components/Marketplace/MetaTags.tsx`
  espelha a mesma informação usando `<Head>` do Inertia, garantindo que navegações
  client-side (sem reload) atualizem `<title>` e Open Graph corretamente. Cada página
  (`Landing`, `Browse`, `PluginDetail`, `Compare`, `PublisherProfile`) chama
  `<MetaTags />` no topo do return.

JSON-LD em `PluginDetail` segue Schema.org `Product` com `Offer`, brand do publisher
e canonical URL — isso habilita rich results no Google (preço, disponibilidade,
breadcrumbs visuais).

### Sitemap + robots.txt

- `GET /sitemap.xml` → `SitemapController` invokable. Inclui rotas estáticas
  (landing, browse), todos os plugins `published` e todos os publishers. Cache de
  1h em `cache()->remember('marketplace:sitemap', 3600, ...)` para amortecer
  crawls agressivos.
- `public/robots.txt` permite tudo, bloqueia `/checkout/` e aponta para o sitemap.
