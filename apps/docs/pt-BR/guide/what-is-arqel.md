# O que é Arqel?

**Arqel** é um framework open-source MIT para construir admin panels em **Laravel 12+/13** com **Inertia 3** e **React 19.2+**. Posiciona-se como alternativa ao Filament e Laravel Nova.

## Filosofia

Três escolhas opinativas formam o coração do projeto:

### 1. Server-driven UI

Resources, Fields, Tables e Forms são declarados em **PHP**. O front-end React apenas consome o JSON serializado pelo servidor. Você descreve **o que** o admin é, não **como** os componentes são montados:

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

Esse arquivo gera index, create, edit, show, routes, validation rules e Inertia payload.

### 2. Inertia-only

Arqel **proíbe** TanStack Query, SWR ou outras fetch libs no Resource CRUD ([ADR-001](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)). Inertia props são o estado default. O resultado: zero impedance mismatch entre Laravel e React, navegação SSR-natural, e um modelo mental único — "props vêm do servidor, callbacks voltam pro servidor".

### 3. Laravel-native

Policies, Gates, FormRequest, Eloquent — usados directamente. Arqel não tem `Role` model, não tem permission table, não tem ACL paralela. Se você sabe Laravel, sabe Arqel.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.3+ · Laravel 12+ |
| Bridge | Inertia 3 |
| Frontend | React 19.2+ · TypeScript 5.6+ strict |
| UI | shadcn CLI v4 (new-york) · Radix UI · Tailwind CSS v4 |
| Tabelas | TanStack Table v8 |
| Bundler | Vite 5 (app) · tsup (libs) |
| Testes | Pest 3 · Vitest · Playwright |

## Pacotes

### Instalação one-line

```bash
composer require arqel-dev/arqel
```

O meta-package `arqel-dev/arqel` puxa todo o stack PHP. Se quiseres saber o que há por baixo:

### PHP

| Pacote | Responsabilidade |
|---|---|
| `arqel-dev/arqel` | Meta-package — agrega tudo abaixo |
| `arqel-dev/core` | Panels, Resources, rotas polimórficas, Inertia bridge, command palette, telemetry |
| `arqel-dev/auth` | Login/Register/Forgot/Reset/Verify bundled (Inertia React pages) + AbilityRegistry |
| `arqel-dev/fields` | 21 field types + ValidationBridge |
| `arqel-dev/table` | Table builder + Columns + Filters |
| `arqel-dev/form` | Form builder + Layout components + FormRequest gen |
| `arqel-dev/actions` | RowAction, BulkAction, ToolbarAction, HeaderAction |
| `arqel-dev/nav` | NavigationItem, NavigationGroup, BreadcrumbsBuilder |

### JavaScript

Os pacotes JS são instalados automaticamente pelo `arqel:install`:

| Pacote | Responsabilidade |
|---|---|
| `@arqel-dev/types` | TypeScript types partilhados (zero runtime) |
| `@arqel-dev/react` | `createArqelApp`, `<ArqelProvider>`, `<ThemeProvider>`, contexts |
| `@arqel-dev/hooks` | `useResource`, `useArqelForm`, `useTable`, `useNavigation`, `useFlash`, `useCanAccess` |
| `@arqel-dev/ui` | AppShell + Sidebar (shadcn `sidebar-07`) + DataTable + FormRenderer + 16 primitivas shadcn |
| `@arqel-dev/auth` | LoginPage / RegisterPage / ForgotPasswordPage / ResetPasswordPage / VerifyEmailNoticePage (split-screen `login-04`) |
| `@arqel-dev/fields` | 21 rich inputs registados via `FieldRegistry` |
| `@arqel-dev/theme` | Tokens semânticos + dark-mode + ThemeToggle (FOUC-safe) |

## Comparação com Filament e Nova

| Critério | Filament | Nova | Arqel |
|---|---|---|---|
| Bridge | Livewire | Vue/Inertia | **Inertia + React** |
| Stack frontend | Alpine + Tailwind | Vue 3 | **React 19 + shadcn (Radix) + Tailwind v4** |
| Licença | MIT | Comercial | **MIT** |
| Validation client | parcial | parcial | **Zod via ValidationBridge** |
| TypeScript | — | parcial | **strict + exactOptionalPropertyTypes** |
| Field count | 30+ | 25+ | **21 cobrindo casos canônicos** |

## Não-objetivos

Arqel **não** vai entregar:

- Form builder visual (drag-drop) — declarative-only
- Multi-tenancy completo na Fase 1 — apenas scaffold
- Real-time collab — Phase 4 considera Laravel Reverb
- ORM custom — só Eloquent

## Próximos passos

- [Getting Started](/pt-BR/guide/getting-started) — setup em < 5 min
- [Panels](/pt-BR/guide/panels) — multi-panel architecture
- [Resources](/pt-BR/guide/resources) — declarar models
- [Roadmap](https://github.com/arqel-dev/arqel/blob/main/PLANNING/07-roadmap-fases.md) — 4 fases, 328 tickets
