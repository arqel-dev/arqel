# ¿Qué es Arqel?

**Arqel** es un framework open-source con licencia MIT para construir admin panels en **Laravel 12+/13** con **Inertia 3** y **React 19.2+**. Se posiciona como una alternativa a Filament y Laravel Nova.

## Filosofía

Tres decisiones opinionadas forman el corazón del proyecto:

### 1. UI dirigida por el servidor

Los Resources, Fields, Tables y Forms se declaran en **PHP**. El front-end React solo consume el JSON serializado por el servidor. Tu describes **qué** es el admin, no **cómo** se ensamblan los componentes:

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

Ese único archivo genera index, create, edit, show, rutas, reglas de validación y payload de Inertia.

### 2. Solo Inertia

Arqel **prohíbe** TanStack Query, SWR u otras librerías de fetch en el CRUD de Resources ([ADR-001](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)). Las props de Inertia son el estado por defecto. El resultado: cero impedance mismatch entre Laravel y React, navegación SSR natural, y un único modelo mental — "las props vienen del servidor, los callbacks vuelven al servidor".

### 3. Nativo de Laravel

Policies, Gates, FormRequest, Eloquent — usados directamente. Arqel no tiene modelo `Role`, ni tabla de permisos, ni ACL paralelo. Si conoces Laravel, conoces Arqel.

## Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.3+ · Laravel 12+ |
| Bridge | Inertia 3 |
| Frontend | React 19.2+ · TypeScript 5.6+ strict |
| UI | shadcn CLI v4 (new-york) · Radix UI · Tailwind CSS v4 |
| Tablas | TanStack Table v8 |
| Bundler | Vite 5 (app) · tsup (libs) |
| Tests | Pest 3 · Vitest · Playwright |

## Paquetes

### Instalación en una línea

```bash
composer require arqel-dev/arqel
```

El meta-paquete `arqel-dev/arqel` arrastra todo el stack PHP. Si quieres saber qué hay debajo:

### PHP

| Paquete | Responsabilidad |
|---|---|
| `arqel-dev/arqel` | Meta-paquete — agrupa todo lo de abajo |
| `arqel-dev/core` | Panels, Resources, rutas polimórficas, bridge de Inertia, command palette, telemetría |
| `arqel-dev/auth` | Login/Register/Forgot/Reset/Verify incluido (páginas Inertia React) + AbilityRegistry |
| `arqel-dev/fields` | 21 tipos de Field + ValidationBridge |
| `arqel-dev/table` | Table builder + Columns + Filters |
| `arqel-dev/form` | Form builder + componentes Layout + generación de FormRequest |
| `arqel-dev/actions` | RowAction, BulkAction, ToolbarAction, HeaderAction |
| `arqel-dev/nav` | NavigationItem, NavigationGroup, BreadcrumbsBuilder |

### JavaScript

Los paquetes JS se instalan automáticamente con `arqel:install`:

| Paquete | Responsabilidad |
|---|---|
| `@arqel-dev/types` | Tipos TypeScript compartidos (cero runtime) |
| `@arqel-dev/react` | `createArqelApp`, `<ArqelProvider>`, `<ThemeProvider>`, contextos |
| `@arqel-dev/hooks` | `useResource`, `useArqelForm`, `useTable`, `useNavigation`, `useFlash`, `useCanAccess` |
| `@arqel-dev/ui` | AppShell + Sidebar (shadcn `sidebar-07`) + DataTable + FormRenderer + 16 primitivas shadcn |
| `@arqel-dev/auth` | LoginPage / RegisterPage / ForgotPasswordPage / ResetPasswordPage / VerifyEmailNoticePage (split-screen `login-04`) |
| `@arqel-dev/fields` | 21 inputs ricos registrados vía `FieldRegistry` |
| `@arqel-dev/theme` | Tokens semánticos + dark-mode + ThemeToggle (FOUC-safe) |

## Comparación con Filament y Nova

| Criterio | Filament | Nova | Arqel |
|---|---|---|---|
| Bridge | Livewire | Vue/Inertia | **Inertia + React** |
| Stack frontend | Alpine + Tailwind | Vue 3 | **React 19 + shadcn (Radix) + Tailwind v4** |
| Licencia | MIT | Comercial | **MIT** |
| Validación de cliente | parcial | parcial | **Zod vía ValidationBridge** |
| TypeScript | — | parcial | **strict + exactOptionalPropertyTypes** |
| Cantidad de Fields | 30+ | 25+ | **21 cubriendo casos canónicos** |

## No-objetivos

Arqel **no** incluirá:

- Form builder visual (drag-drop) — solo declarativo
- Multi-tenancy completo en Fase 1 — solo scaffold
- Colaboración en tiempo real — Fase 4 considera Laravel Reverb
- ORM propio — solo Eloquent

## Próximos pasos

- [Empezando](/es/guide/getting-started) — setup en < 5 min
- [Panels](/es/guide/panels) — arquitectura multi-panel
- [Resources](/es/guide/resources) — declarar modelos
- [Roadmap](https://github.com/arqel-dev/arqel/blob/main/PLANNING/07-roadmap-fases.md) — 4 fases, 328 tickets
