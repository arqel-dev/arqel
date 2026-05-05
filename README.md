# Arqel

> Admin panels for Laravel, forged in PHP, rendered in React.

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Status](https://img.shields.io/badge/status-early%20development-orange.svg)]()

🌍 **Read this in your language:** [English](README.md) · [Português (BR)](README.pt-BR.md) · [Español](README.es.md)

## Status

🚧 **Early development.** Not production-ready.

Phases 1, 2 and 3 closed. Phase 4 in progress (DevTools extension, CLI, marketplace, Laravel Cloud). See `docs/tickets/current.md` for the live snapshot.

## What is Arqel

Open-source MIT framework for building Laravel admin panels with a modern React UI. Positioning: **Filament/Nova reimagined with React 19.2+**, focused on:

- **Laravel-native** — uses Policies, FormRequest, Eloquent and Gate as-is
- **Inertia 3 as the only bridge** — PHP declares, React renders, zero fetch boilerplate
- **TypeScript first-class** — types generated and shipped for every contract
- **shadcn UI (Radix)** — accessible, copy-paste customizable, dark mode out of the box
- **AI-native** — first-class MCP server + AI fields starting in Phase 2-3
- **Realtime** — collaborative editing via Laravel Reverb + Yjs

See `PLANNING/01-spec-tecnica.md` for the full spec.

## Stack

- PHP 8.3+ · Laravel 12+ · Pest 3
- React 19.2+ · TypeScript 5.6+ strict · Inertia 3
- Tailwind v4 · Radix UI · shadcn CLI v4 (new-york)
- Vite 5 · pnpm workspaces · Composer path repositories
- GitHub Actions · splitsh/lite for monorepo splitting

## Quickstart

The full installation lives at [`apps/docs/guide/installation.md`](apps/docs/guide/installation.md). The short version:

```bash
# 1. Create a fresh Laravel 12 app
composer create-project laravel/laravel my-admin-app
cd my-admin-app

# 2. Install Arqel
composer require arqel-dev/arqel

# 3. Run the installer
php artisan arqel:install

# 4. Set up the database + first admin
php artisan migrate
php artisan arqel:make-user

# 5. Run the dev servers (two terminals)
php artisan serve
pnpm dev
```

Open http://localhost:8000/admin/login.

The installer scaffolds the Service Provider, the `HandleInertiaRequests` middleware (with `rootView = 'arqel.layout'`), `vite.config.ts`, the example `UserResource`, the Inertia Blade root and the hero illustration. It also auto-registers the provider in `bootstrap/providers.php`. **No manual `bootstrap/app.php` edits required.**

## Monorepo layout

```
arqel/
├── packages/             # PHP packages (Composer)
│   ├── arqel/            # meta-package (composer require arqel-dev/arqel)
│   ├── core/             # panels, resources, polymorphic routes, Inertia bridge
│   ├── auth/             # bundled login / register / reset / verify
│   ├── fields/           # Field schema types
│   ├── form/             # form rendering server-side
│   ├── actions/          # action contracts + invokers
│   ├── nav/              # navigation builder
│   └── table/            # table query / sort / filter / paginate
├── packages-js/          # JS packages (npm)
│   ├── types/            # TS types shared by Inertia payloads
│   ├── react/            # createArqelApp + ArqelProvider + ThemeProvider
│   ├── hooks/            # useResource / useTable / useNavigation / …
│   ├── ui/               # shadcn primitives + AppShell + Sidebar + DataTable
│   ├── auth/             # Inertia React pages (Login/Register/…)
│   ├── theme/            # tokens + ThemeToggle
│   ├── fields-js/        # native field renderers
│   ├── fields-advanced/  # rich-text / repeater / wizard / builder / etc.
│   ├── ai/               # AI input components
│   ├── workflow/         # state machine UI
│   ├── versioning/       # version timeline + diff
│   ├── realtime/         # connection status, presence
│   ├── i18n/             # locale switcher + translator
│   └── a11y/             # SkipLink, VisuallyHidden, LiveRegion
├── apps/
│   ├── docs/             # VitePress documentation site (i18n)
│   └── demo/             # end-to-end showcase app (recreated via arqel:install)
└── PLANNING/             # internal planning (13 docs, 328 tickets)
```

Details in `PLANNING/04-repo-structure.md`.

## Roadmap

| Phase | Release | Duration | Status |
|---|---|---|---|
| Phase 1 — MVP | v0.5-beta | 4-7 months | ✅ Closed |
| Phase 2 — Essentials | v0.8-rc | 4-7 months | ✅ Closed |
| Phase 3 — Advanced | v1.0 LTS | 7-10 months | ✅ Closed |
| Phase 4 — Ecosystem | v1.x+ | 12+ months | 🚧 In progress |

Total: **328 detailed tickets** in `PLANNING/08-*.md` through `PLANNING/11-*.md`.

## Working on the framework itself

If you want to hack on Arqel (rather than use it in your app):

```bash
git clone https://github.com/arqel-dev/arqel.git
cd arqel
nvm use                         # if you use nvm
./scripts/init.sh               # bootstrap pnpm + composer
pnpm test:all                   # lint + typecheck + tests across packages
```

Common commands:

```bash
pnpm install                    # workspace JS deps
composer install                # path-repository PHP deps
pnpm build                      # build every package
pnpm test                       # tests across the workspace
pnpm lint                       # biome + pint
pnpm typecheck                  # tsc --noEmit + phpstan
pnpm dev                        # watch mode
```

The end-to-end demo (recreated from `arqel:install` itself) lives in `apps/demo/`.

## Contributing

Contributions welcome — see [`CONTRIBUTING.md`](CONTRIBUTING.md) and [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md).

**Requirements:**
- DCO sign-off on every commit: `git commit --signoff`
- Conventional Commits: `feat(pkg): description`
- Tests required
- ADRs respected (see `PLANNING/03-adrs.md`)

**Security:** see [`SECURITY.md`](SECURITY.md) for the responsible disclosure policy.

## Documentation

- **Planning:** `PLANNING/` (13 docs, canonical source)
- **For AI agents:** `AGENTS.md`, `CLAUDE.md`
- **Public site:** https://arqel.dev (coming soon — `apps/docs/` builds the VitePress site, available in EN / PT-BR / ES)

## License

MIT — see [`LICENSE`](LICENSE).

## Credits

Inspired by:
- [Filament](https://filamentphp.com) — defined the Laravel admin-panel pattern
- [Laravel Nova](https://nova.laravel.com) — the Resource model
- [React Admin](https://marmelab.com/react-admin/) — the client-side admin model
- [shadcn/ui](https://ui.shadcn.com) — the copy-paste component philosophy
