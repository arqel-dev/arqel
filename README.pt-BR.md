# Arqel

> Painéis admin para Laravel, forjados em PHP, renderizados em React.

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Status](https://img.shields.io/badge/status-early%20development-orange.svg)]()

🌍 **Leia em outros idiomas:** [English](README.md) · [Português (BR)](README.pt-BR.md) · [Español](README.es.md)

## Status

🚧 **Em desenvolvimento.** Não pronto para produção.

Fases 1, 2 e 3 fechadas. Fase 4 em andamento (DevTools extension, CLI, marketplace, Laravel Cloud). Ver `docs/tickets/current.md` para o snapshot atual.

## O que é Arqel

Framework MIT open-source para construir painéis admin em Laravel com UI React moderna. Posicionamento: **Filament/Nova reimaginado com React 19.2+**, com foco em:

- **Laravel-nativo** — usa Policies, FormRequest, Eloquent e Gate como vêm
- **Inertia 3 como única ponte** — PHP declara, React renderiza, zero boilerplate de fetch
- **TypeScript first-class** — types gerados e fornecidos para todos os contratos
- **shadcn UI (Radix)** — acessível, customizável por copy-paste, dark mode incluído
- **AI-native** — servidor MCP nativo + AI fields desde a Fase 2-3
- **Realtime** — edição colaborativa via Laravel Reverb + Yjs

Ver `PLANNING/01-spec-tecnica.md` para a spec completa.

## Stack

- PHP 8.3+ · Laravel 12+ · Pest 3
- React 19.2+ · TypeScript 5.6+ strict · Inertia 3
- Tailwind v4 · Radix UI · shadcn CLI v4 (new-york)
- Vite 5 · pnpm workspaces · Composer path repositories
- GitHub Actions · splitsh/lite para split do monorepo

## Início rápido

A instalação completa está em [`apps/docs/pt-BR/guide/installation.md`](apps/docs/pt-BR/guide/installation.md). Versão curta:

```bash
# 1. Cria um Laravel 12 fresh
composer create-project laravel/laravel my-admin-app
cd my-admin-app

# 2. Instala o Arqel
composer require arqel-dev/framework

# 3. Corre o instalador
php artisan arqel:install

# 4. Setup da base de dados + primeiro admin
php artisan migrate
php artisan arqel:make-user

# 5. Arranca os dev servers (dois terminais)
php artisan serve
pnpm dev
```

Abre http://localhost:8000/admin/login.

O instalador faz scaffold do Service Provider, do middleware `HandleInertiaRequests` (com `rootView = 'arqel.layout'`), `vite.config.ts`, o exemplo `UserResource`, o Blade root e a ilustração hero. Também auto-regista o provider em `bootstrap/providers.php`. **Sem edits manuais ao `bootstrap/app.php`.**

## Layout do monorepo

```
arqel/
├── packages/             # Pacotes PHP (Composer)
│   ├── arqel/            # meta-package (composer require arqel-dev/framework)
│   ├── core/             # panels, resources, rotas polimórficas, Inertia bridge
│   ├── auth/             # login / register / reset / verify bundled
│   ├── fields/           # tipos de Field schema
│   ├── form/             # render server-side de forms
│   ├── actions/          # contratos + invokers de actions
│   ├── nav/              # construtor de navegação
│   └── table/            # query / sort / filter / paginate
├── packages-js/          # Pacotes JS (npm)
│   ├── types/            # TS types partilhados pelos payloads Inertia
│   ├── react/            # createArqelApp + ArqelProvider + ThemeProvider
│   ├── hooks/            # useResource / useTable / useNavigation / …
│   ├── ui/               # primitivas shadcn + AppShell + Sidebar + DataTable
│   ├── auth/             # Inertia React pages (Login/Register/…)
│   ├── theme/            # tokens + ThemeToggle
│   ├── fields-js/        # renderers nativos de fields
│   ├── fields-advanced/  # rich-text / repeater / wizard / builder / etc.
│   ├── ai/               # componentes de input AI
│   ├── workflow/         # UI de state machine
│   ├── versioning/       # version timeline + diff
│   ├── realtime/         # connection status, presença
│   ├── i18n/             # locale switcher + translator
│   └── a11y/             # SkipLink, VisuallyHidden, LiveRegion
├── apps/
│   ├── docs/             # site VitePress (i18n)
│   └── demo/             # showcase app end-to-end (recriado via arqel:install)
└── PLANNING/             # planejamento interno (13 docs, 328 tickets)
```

Detalhes em `PLANNING/04-repo-structure.md`.

## Roadmap

| Fase | Release | Duração | Status |
|---|---|---|---|
| Fase 1 — MVP | v0.5-beta | 4-7 meses | ✅ Fechada |
| Fase 2 — Essenciais | v0.8-rc | 4-7 meses | ✅ Fechada |
| Fase 3 — Avançadas | v1.0 LTS | 7-10 meses | ✅ Fechada |
| Fase 4 — Ecossistema | v1.x+ | 12+ meses | 🚧 Em progresso |

Total: **328 tickets detalhados** em `PLANNING/08-*.md` a `PLANNING/11-*.md`.

## Trabalhar no framework

Se quiseres mexer no Arqel em si (em vez de usá-lo na tua app):

```bash
git clone https://github.com/arqel-dev/arqel.git
cd arqel
nvm use                         # se usas nvm
./scripts/init.sh               # bootstrap pnpm + composer
pnpm test:all                   # lint + typecheck + tests dos packages
```

Comandos comuns:

```bash
pnpm install                    # workspace JS deps
composer install                # path-repository PHP deps
pnpm build                      # build de todos os packages
pnpm test                       # tests em todo o workspace
pnpm lint                       # biome + pint
pnpm typecheck                  # tsc --noEmit + phpstan
pnpm dev                        # watch mode
```

O demo end-to-end (recriado a partir do `arqel:install`) vive em `apps/demo/`.

## Contribuir

Contribuições são bem-vindas — ver [`CONTRIBUTING.md`](CONTRIBUTING.md) e [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md).

**Requisitos:**
- DCO sign-off em todos os commits: `git commit --signoff`
- Conventional Commits: `feat(pkg): descrição`
- Testes obrigatórios
- ADRs respeitados (ver `PLANNING/03-adrs.md`)

**Segurança:** ver [`SECURITY.md`](SECURITY.md) para a política de divulgação responsável.

## Documentação

- **Planejamento:** `PLANNING/` (13 docs, fonte canônica)
- **Para AI agents:** `AGENTS.md`, `CLAUDE.md`
- **Site público:** https://arqel.dev (em breve — `apps/docs/` faz build do site VitePress, disponível em EN / PT-BR / ES)

## Licença

MIT — ver [`LICENSE`](LICENSE).

## Créditos

Inspirado por:
- [Filament](https://filamentphp.com) — definiu o padrão Laravel admin panel
- [Laravel Nova](https://nova.laravel.com) — o modelo Resource
- [React Admin](https://marmelab.com/react-admin/) — o modelo client-side de admin
- [shadcn/ui](https://ui.shadcn.com) — a filosofia copy-paste de componentes
