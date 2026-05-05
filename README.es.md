# Arqel

> Paneles admin para Laravel, forjados en PHP, renderizados en React.

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Status](https://img.shields.io/badge/status-early%20development-orange.svg)]()

🌍 **Lee en otros idiomas:** [English](README.md) · [Português (BR)](README.pt-BR.md) · [Español](README.es.md)

## Estado

🚧 **En desarrollo.** No listo para producción.

Phases 1, 2 y 3 cerradas. Phase 4 en curso (DevTools extension, CLI, marketplace, Laravel Cloud). Ver `docs/tickets/current.md` para el snapshot vivo.

## Qué es Arqel

Framework MIT open-source para construir paneles admin en Laravel con UI React moderno. Posicionamiento: **Filament/Nova reimaginado con React 19.2+**, enfocado en:

- **Laravel-nativo** — usa Policies, FormRequest, Eloquent y Gate tal cual
- **Inertia 3 como única puente** — PHP declara, React renderiza, sin boilerplate de fetch
- **TypeScript first-class** — types generados y enviados para cada contrato
- **shadcn UI (Radix)** — accesible, customizable por copy-paste, dark mode out of the box
- **AI-native** — servidor MCP nativo + AI fields desde Phase 2-3
- **Realtime** — edición colaborativa vía Laravel Reverb + Yjs

Ver `PLANNING/01-spec-tecnica.md` para la spec completa.

## Stack

- PHP 8.3+ · Laravel 12+ · Pest 3
- React 19.2+ · TypeScript 5.6+ strict · Inertia 3
- Tailwind v4 · Radix UI · shadcn CLI v4 (new-york)
- Vite 5 · pnpm workspaces · Composer path repositories
- GitHub Actions · splitsh/lite para split del monorepo

## Inicio rápido

La instalación completa está en [`apps/docs/es/guide/installation.md`](apps/docs/es/guide/installation.md). Versión corta:

```bash
# 1. Crea un Laravel 12 fresh
composer create-project laravel/laravel my-admin-app
cd my-admin-app

# 2. Instala Arqel
composer require arqel-dev/framework

# 3. Ejecuta el instalador
php artisan arqel:install

# 4. Setup de la base de datos + primer admin
php artisan migrate
php artisan arqel:make-user

# 5. Levanta los dev servers (dos terminales)
php artisan serve
pnpm dev
```

Abre http://localhost:8000/admin/login.

El instalador hace scaffold del Service Provider, del middleware `HandleInertiaRequests` (con `rootView = 'arqel.layout'`), `vite.config.ts`, el ejemplo `UserResource`, el Blade root y la ilustración hero. También auto-registra el provider en `bootstrap/providers.php`. **Sin edits manuales a `bootstrap/app.php`.**

## Layout del monorepo

```
arqel/
├── packages/             # Paquetes PHP (Composer)
│   ├── arqel/            # meta-package (composer require arqel-dev/framework)
│   ├── core/             # panels, resources, rutas polimórficas, Inertia bridge
│   ├── auth/             # login / register / reset / verify bundled
│   ├── fields/           # tipos de Field schema
│   ├── form/             # render server-side de forms
│   ├── actions/          # contratos + invokers de actions
│   ├── nav/              # constructor de navegación
│   └── table/            # query / sort / filter / paginate
├── packages-js/          # Paquetes JS (npm)
│   ├── types/            # TS types compartidos por payloads Inertia
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
│   ├── realtime/         # connection status, presence
│   ├── i18n/             # locale switcher + translator
│   └── a11y/             # SkipLink, VisuallyHidden, LiveRegion
├── apps/
│   ├── docs/             # sitio VitePress (i18n)
│   └── demo/             # showcase app end-to-end (recreado vía arqel:install)
└── PLANNING/             # planificación interna (13 docs, 328 tickets)
```

Detalles en `PLANNING/04-repo-structure.md`.

## Roadmap

| Phase | Release | Duración | Estado |
|---|---|---|---|
| Phase 1 — MVP | v0.5-beta | 4-7 meses | ✅ Cerrada |
| Phase 2 — Essentials | v0.8-rc | 4-7 meses | ✅ Cerrada |
| Phase 3 — Advanced | v1.0 LTS | 7-10 meses | ✅ Cerrada |
| Phase 4 — Ecosystem | v1.x+ | 12+ meses | 🚧 En curso |

Total: **328 tickets detallados** en `PLANNING/08-*.md` a `PLANNING/11-*.md`.

## Trabajar en el framework

Si quieres hackear Arqel en sí (no usarlo en tu app):

```bash
git clone https://github.com/arqel-dev/arqel.git
cd arqel
nvm use                         # si usas nvm
./scripts/init.sh               # bootstrap pnpm + composer
pnpm test:all                   # lint + typecheck + tests de los packages
```

Comandos comunes:

```bash
pnpm install                    # workspace JS deps
composer install                # path-repository PHP deps
pnpm build                      # build de todos los packages
pnpm test                       # tests en todo el workspace
pnpm lint                       # biome + pint
pnpm typecheck                  # tsc --noEmit + phpstan
pnpm dev                        # watch mode
```

El demo end-to-end (recreado desde `arqel:install`) vive en `apps/demo/`.

## Contribuir

Contribuciones bienvenidas — ver [`CONTRIBUTING.md`](CONTRIBUTING.md) y [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md).

**Requisitos:**
- DCO sign-off en cada commit: `git commit --signoff`
- Conventional Commits: `feat(pkg): descripción`
- Tests obligatorios
- ADRs respetados (ver `PLANNING/03-adrs.md`)

**Seguridad:** ver [`SECURITY.md`](SECURITY.md) para la política de divulgación responsable.

## Documentación

- **Planning:** `PLANNING/` (13 docs, fuente canónica)
- **Para AI agents:** `AGENTS.md`, `CLAUDE.md`
- **Sitio público:** https://arqel.dev (próximamente — `apps/docs/` hace build del sitio VitePress, disponible en EN / PT-BR / ES)

## Licencia

MIT — ver [`LICENSE`](LICENSE).

## Créditos

Inspirado por:
- [Filament](https://filamentphp.com) — definió el patrón Laravel admin panel
- [Laravel Nova](https://nova.laravel.com) — el modelo Resource
- [React Admin](https://marmelab.com/react-admin/) — el modelo client-side de admin
- [shadcn/ui](https://ui.shadcn.com) — la filosofía copy-paste de componentes
