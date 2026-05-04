# Arqel Demo App — Blog Admin Showcase

> **Demo end-to-end** do framework Arqel: um blog admin minimal que integra
> Resources, Fields (incluindo AI fields), Workflow, Versioning e Audit num
> único projeto Laravel + Inertia + React.

Este é o **app canônico** para:

- Visitantes que querem rodar Arqel local em **2 minutos**.
- Screenshots da documentação e do site marketing.
- Smoke test cross-package no CI.
- Base para tutoriais "do zero ao painel" (`docs/guides/getting-started`).

---

## Sumário

- [Stack](#stack)
- [Setup local](#setup-local)
- [Tour das features](#tour-das-features)
- [Estrutura de diretórios](#estrutura-de-diretórios)
- [Pacotes integrados](#pacotes-integrados)
- [Como rodar testes](#como-rodar-testes)
- [Screenshots](#screenshots)
- [FAQ](#faq)

---

## Stack

| Camada | Versão |
|---|---|
| PHP | 8.3+ |
| Laravel | 12.x ou 13.x |
| Inertia | 3.x |
| React | 19.2+ |
| TypeScript | 5.6+ (strict + `noUncheckedIndexedAccess`) |
| Tailwind | v4 |
| Pest | 3.x |
| Vitest | 2.x |

Distribuído sob licença **MIT**, ponto de entrada do showcase de release `0.8.0-rc.1`.

---

## Setup local

### 1. Pré-requisitos

```bash
php --version    # 8.3+
node --version   # 20.9+ LTS
composer --version
corepack enable  # habilita pnpm
```

### 2. Instalar dependências

A partir da raiz do monorepo:

```bash
# PHP — usa path repositories para os pacotes locais
composer -d apps/demo install

# JS — pnpm workspace já cobre o app/demo
pnpm install
```

### 3. Banco de dados (SQLite default)

```bash
cd apps/demo
touch database/database.sqlite
php artisan migrate --seed --seeder=Database\\Seeders\\DemoSeeder
```

O seeder cria:

- **3 usuários** (Ada, Alan, Grace) com email `<slug>@arqel.dev`.
- **5 categorias** (Engineering, Product, Design, DevOps, Community).
- **20 tags** (laravel, react, inertia, php, …) classificadas em frontend/
  backend/devops/design/product.
- **50 posts** distribuídos entre `draft`, `review`, `published` e `archived`.

### 4. Rodar dev server

```bash
# Em terminal #1
php artisan serve   # http://127.0.0.1:8000

# Em terminal #2
pnpm --filter @arqel/demo-app dev
```

Acesse:

- `http://127.0.0.1:8000/admin` — dashboard.
- `http://127.0.0.1:8000/admin/login` — login (provedor `arqel/auth`).
- `http://127.0.0.1:8000/admin/posts` — listagem com Table + Filters.
- `http://127.0.0.1:8000/admin/posts/create` — formulário com fields demonstrativos.
- `http://127.0.0.1:8000/admin/tags` — listagem com aiSelect classifier.

---

## Tour das features

### Field types em ação

O `PostResource` demonstra cinco tipos de field representativos:

| Field | Tipo | Demonstra |
|---|---|---|
| `title` | `text` | Validação obrigatória + slug source |
| `slug` | `slug` | Auto-slug a partir de `title` |
| `summary` | `aiText` | Geração via `arqel/ai` (prompt configurado) |
| `body` | `richText` | Editor TipTap-style |
| `state` | `stateTransition` | Workflow visual com botões de transição |
| `published_at` | `dateTime` | Date + time picker com timezone awareness |
| `author_id` | `belongsTo` | Searchable select para `UserResource` |

### Workflow

State machine declarado em `app/States/PostStates.php`:

```
pending → draft → review → published → archived
            ↑       ↓
            └───────┘   (review pode voltar para draft)
```

A transição é exposta via endpoint `POST /admin/posts/{id}/transition` que
valida transições inválidas (HTTP 422 quando o destino não está em
`PostStates::allowedFrom()`).

### AI generation

- **`Field::aiText('summary')`** — botão "Generate" no form chama
  `POST /admin/posts/ai/summary` que delega ao provider configurado.
  No demo, retorna stub determinístico (`arqel-ai-stub`) para funcionar offline.
- **`Field::aiSelect('category')->classifyFromFields(['name', 'description'])`** —
  classifica tags automaticamente baseado no conteúdo. Chama
  `POST /admin/tags/ai/classify`.

### Versioning

`Post` usa o trait `Versionable`. Cada save() incrementa `versionCount` (no
demo) — em produção o pacote `arqel/versioning` persiste snapshots completos
em `arqel_versions` e expõe UI de "Histórico" + restore.

### Audit log

Plugins `arqel/audit` registram todas as ações user-driven (create, update,
delete, transition). UI acessível em `/admin/audit-log` quando o pacote
estiver registrado no painel.

---

## Estrutura de diretórios

```
apps/demo/
├── app/
│   ├── Arqel/
│   │   ├── Panel.php              # Stub leve do builder
│   │   └── Resources/             # PostResource, TagResource, CategoryResource, UserResource
│   ├── Http/Controllers/          # Dashboard + Posts + Tags + AI endpoints
│   ├── Models/                    # Post, Tag, Category, User + Concerns/
│   ├── Policies/                  # PostPolicy
│   ├── Providers/                 # ArqelServiceProvider (user-land)
│   └── States/                    # PostStates (workflow)
├── database/
│   ├── migrations/
│   └── seeders/                   # DemoSeeder
├── resources/
│   ├── css/app.css
│   ├── js/
│   │   ├── Pages/Admin/           # Dashboard, Posts/Index, Posts/Create, Tags/Index
│   │   ├── __tests__/             # Vitest specs
│   │   ├── app.tsx
│   │   └── types.ts
│   └── views/app.blade.php
├── routes/web.php
├── tests/
│   ├── Feature/                   # Pest specs (PHP)
│   ├── Fixtures/                  # views + helpers
│   └── TestCase.php
├── composer.json
├── package.json
├── phpunit.xml
├── tsconfig.json
└── vite.config.ts
```

---

## Pacotes integrados

O `composer.json` declara path repositories para:

| Pacote | Função no demo |
|---|---|
| `arqel/core` | `Panel`, `Resource`, registry, command palette |
| `arqel/fields` | `Field::text()`, `slug()`, `dateTime()`, `belongsTo()`, `richText()` |
| `arqel/auth` | Login, registration, email verification, password reset |
| `arqel/table` | Listagem de Posts/Tags com filters + sort |
| `arqel/form` | Form runtime (validação client+server, dirty state, autosave) |
| `arqel/actions` | Bulk actions (publish, archive) e row actions |
| `arqel/nav` | Sidebar, breadcrumbs, command palette |
| `arqel/ai` | `aiText`, `aiSelect`, providers (OpenAI/Anthropic) |
| `arqel/workflow` | `HasWorkflow` trait + `stateTransition` field |
| `arqel/versioning` | `Versionable` trait + version history UI |
| `arqel/audit` | Audit log automático para todas as ações |

`package.json` complementa com pacotes JS `@arqel/react`, `@arqel/ui`,
`@arqel/auth`, `@arqel/workflow`, `@arqel/versioning`, `@arqel/ai`.

---

## Como rodar testes

### PHP (Pest 3)

```bash
cd apps/demo
vendor/bin/pest
```

Suítes:

- `DemoBootstrapTest` — registro do painel + migrations + seeder.
- `PostResourceTest` — list, create form, AI summary, workflow transition.
- `TagResourceTest` — list + AI classify.
- `AdminDashboardTest` — render do dashboard com stats reais.

### JavaScript (Vitest)

```bash
pnpm --filter @arqel/demo-app test
```

Suítes:

- `Dashboard.test.tsx`
- `PostsIndex.test.tsx`
- `TagsIndex.test.tsx`

### Lint + typecheck

```bash
vendor/bin/pint --test
pnpm --filter @arqel/demo-app typecheck
```

---

## Screenshots

> **Placeholder** — capturas serão adicionadas em `docs/screenshots/demo/`
> conforme as UIs `arqel/auth` e `arqel/table` finalizem suas APIs.

- `demo-dashboard.png`
- `demo-posts-list.png`
- `demo-post-create.png`
- `demo-workflow-transition.png`
- `demo-version-history.png`
- `demo-audit-log.png`

---

## FAQ

**Posso fazer fork e usar de boilerplate?**
Sim — licença MIT. Rode `composer create-project arqel/demo-app meu-blog`
quando o pacote for publicado, ou clone o monorepo e copie `apps/demo/` para
um novo repositório.

**Por que SQLite default?**
Para que o setup seja zero-config. Para Postgres/MySQL, edite `.env` e rode
`php artisan migrate:fresh --seed`.

**O AI funciona sem chave?**
Sim — os endpoints retornam stubs determinísticos. Para usar provider real,
configure `OPENAI_API_KEY` ou `ANTHROPIC_API_KEY` em `.env` e ajuste
`config/arqel-ai.php` (gerado pelo `arqel/ai` package).

**Como adiciono mais Resources?**
Crie classe em `app/Arqel/Resources/` e adicione ao array em
`ArqelServiceProvider::register()`.

---

**Tickets de origem:** ver `PLANNING/08-fase-1-mvp.md` e `apps/demo/SKILL.md`.
