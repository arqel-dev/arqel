# What is Arqel?

**Arqel** is an MIT-licensed open-source framework for building admin panels in **Laravel 12+/13** with **Inertia 3** and **React 19.2+**. It positions itself as an alternative to Filament and Laravel Nova.

## Philosophy

Three opinionated choices form the heart of the project:

### 1. Server-driven UI

Resources, Fields, Tables, and Forms are declared in **PHP**. The React front-end only consumes the JSON serialized by the server. You describe **what** the admin is, not **how** the components are assembled:

```php
final class PostResource extends Resource
{
    protected static string $model = Post::class;

    public function fields(): array
    {
        return [
            Field::text('title')->required(),
            Field::slug('slug')->fromField('title'),
            Field::textarea('body'),
        ];
    }
}
```

That single file generates index, create, edit, show, routes, validation rules, and Inertia payload.

### 2. Inertia-only

Arqel **forbids** TanStack Query, SWR, or other fetch libraries in Resource CRUD ([ADR-001](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)). Inertia props are the default state. The result: zero impedance mismatch between Laravel and React, natural SSR navigation, and a single mental model — "props come from the server, callbacks go back to the server".

### 3. Laravel-native

Policies, Gates, FormRequest, Eloquent — used directly. Arqel has no `Role` model, no permission table, no parallel ACL. If you know Laravel, you know Arqel.

## Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3+ · Laravel 12+ |
| Bridge | Inertia 3 |
| Frontend | React 19.2+ · TypeScript 5.6+ strict |
| UI | shadcn CLI v4 (new-york) · Radix UI · Tailwind CSS v4 |
| Tables | TanStack Table v8 |
| Bundler | Vite 5 (app) · tsup (libs) |
| Tests | Pest 3 · Vitest · Playwright |

## Packages

### One-line installation

```bash
composer require arqel-dev/framework
```

The `arqel-dev/framework` meta-package pulls the entire PHP stack. If you want to know what's underneath:

### PHP

| Package | Responsibility |
|---|---|
| `arqel-dev/framework` | Meta-package — bundles everything below |
| `arqel-dev/core` | Panels, Resources, polymorphic routes, Inertia bridge, command palette, telemetry |
| `arqel-dev/auth` | Login/Register/Forgot/Reset/Verify bundled (Inertia React pages) + AbilityRegistry |
| `arqel-dev/fields` | 21 field types + ValidationBridge |
| `arqel-dev/table` | Table builder + Columns + Filters |
| `arqel-dev/form` | Form builder + Layout components + FormRequest gen |
| `arqel-dev/actions` | RowAction, BulkAction, ToolbarAction, HeaderAction |
| `arqel-dev/nav` | NavigationItem, NavigationGroup, BreadcrumbsBuilder |

### JavaScript

The JS packages are installed automatically by `arqel:install`:

| Package | Responsibility |
|---|---|
| `@arqel-dev/types` | Shared TypeScript types (zero runtime) |
| `@arqel-dev/react` | `createArqelApp`, `<ArqelProvider>`, `<ThemeProvider>`, contexts |
| `@arqel-dev/hooks` | `useResource`, `useArqelForm`, `useTable`, `useNavigation`, `useFlash`, `useCanAccess` |
| `@arqel-dev/ui` | AppShell + Sidebar (shadcn `sidebar-07`) + DataTable + FormRenderer + 16 shadcn primitives |
| `@arqel-dev/auth` | LoginPage / RegisterPage / ForgotPasswordPage / ResetPasswordPage / VerifyEmailNoticePage (split-screen `login-04`) |
| `@arqel-dev/fields` | 21 rich inputs registered via `FieldRegistry` |
| `@arqel-dev/theme` | Semantic tokens + dark-mode + ThemeToggle (FOUC-safe) |

## Comparison with Filament and Nova

| Criteria | Filament | Nova | Arqel |
|---|---|---|---|
| Bridge | Livewire | Vue/Inertia | **Inertia + React** |
| Frontend stack | Alpine + Tailwind | Vue 3 | **React 19 + shadcn (Radix) + Tailwind v4** |
| License | MIT | Commercial | **MIT** |
| Client validation | partial | partial | **Zod via ValidationBridge** |
| TypeScript | — | partial | **strict + exactOptionalPropertyTypes** |
| Field count | 30+ | 25+ | **21 covering canonical cases** |

## Non-goals

Arqel **will not** ship:

- Visual form builder (drag-drop) — declarative-only
- Full multi-tenancy in Phase 1 — only scaffold
- Real-time collab — Phase 4 considers Laravel Reverb
- Custom ORM — Eloquent only

## Next steps

- [Getting Started](/guide/getting-started) — setup in < 5 min
- [Panels](/guide/panels) — multi-panel architecture
- [Resources](/guide/resources) — declare models
- [Roadmap](https://github.com/arqel-dev/arqel/blob/main/PLANNING/07-roadmap-fases.md) — 4 phases, 328 tickets
