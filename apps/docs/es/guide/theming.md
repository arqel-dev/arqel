# Theming

> Sistema de theming de Arqel — dark-mode, tokens semánticos y personalización visual completa de tu panel.

::: tip Los tokens semánticos vienen de shadcn
Tras la migración a **shadcn UI (preset `new-york`) + Radix UI**, los tokens semánticos canónicos (`--background`, `--foreground`, `--primary`, `--border`, `--muted`, `--muted-foreground`, `--destructive`, `--ring`, `--radius`) se definen en `@arqel-dev/ui/styles.css` en los bloques `:root` (light) y `.dark` (dark), con el bridge `@theme inline` para que Tailwind v4 exponga utilidades `bg-background`, `text-foreground`, `border-border`, etc. automáticamente.

El paquete **`@arqel-dev/theme`** sigue existiendo y solo se encarga del **`<ThemeProvider>` React + toggle + snippet anti-FOUC**. Las variables CSS de abajo prefijadas con `--arqel-color-*` son una capa **opcional, legacy** — los proyectos nuevos deberían preferir los tokens shadcn (`--primary` en lugar de `--arqel-color-primary`, etc.).
:::

Arqel ofrece un sistema de theming completo combinando tres piezas:

1. **Tokens semánticos shadcn** en `@arqel-dev/ui/styles.css` — describen intent (`background`, `foreground`, `primary`, `border`, `muted`, `destructive`, `ring`).
2. **`<ThemeProvider>` React** (de `@arqel-dev/theme`) — aplica `dark` en `<html>` según la preferencia del usuario + `prefers-color-scheme`.
3. **Snippet anti-FOUC inline** — evita un flash blanco antes de que React monte.

Todo funciona out-of-the-box en un proyecto Arqel nuevo. Personalizar es solo cuestión de sobrescribir variables CSS.

## Tokens shadcn (canónicos)

```css
/* @arqel-dev/ui/styles.css — ya importado por el scaffold de arqel:install */
:root {
  --background: oklch(1 0 0);
  --foreground: oklch(0.145 0 0);
  --primary: oklch(0.205 0 0);
  --primary-foreground: oklch(0.985 0 0);
  --muted: oklch(0.97 0 0);
  --muted-foreground: oklch(0.556 0 0);
  --border: oklch(0.922 0 0);
  --ring: oklch(0.708 0 0);
  --destructive: oklch(0.577 0.245 27.325);
  --radius: 0.625rem;
  --radius-sm: calc(var(--radius) - 4px);
  --radius-lg: calc(var(--radius) + 4px);
}

.dark {
  --background: oklch(0.145 0 0);
  --foreground: oklch(0.985 0 0);
  /* ... */
}

@theme inline {
  --color-background: var(--background);
  --color-foreground: var(--foreground);
  --color-primary: var(--primary);
  /* ... bridge a las utilidades de Tailwind */
}
```

Para personalizar el brand, sobrescribe `--primary` (y `--primary-foreground` para el texto contrastante) en tu `app.css` **después** de importar `@arqel-dev/ui/styles.css`.

## Setup básico

### 1. Instalar

```bash
pnpm add @arqel-dev/theme
```

### 2. Importar tokens + envolver la app

En tu entry point de Inertia:

```tsx
// resources/js/app.tsx
import { ThemeProvider } from '@arqel-dev/theme';
import '@arqel-dev/theme/tokens.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
  resolve: (name) => {/* ... */},
  setup({ el, App, props }) {
    createRoot(el).render(
      <ThemeProvider>
        <App {...props} />
      </ThemeProvider>,
    );
  },
});
```

### 3. Añadir el snippet anti-FOUC en Blade

Sin esto, los usuarios de dark-mode ven un flash blanco antes de que React monte:

```blade
{{-- resources/views/app.blade.php --}}
<head>
  <script>(function(){try{var k="arqel-theme",t=null;try{t=localStorage.getItem(k)}catch(e){}if(t!=="light"&&t!=="dark"&&t!=="system")t="system";var r=t==="system"?(window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"):t;var el=document.documentElement;if(r==="dark")el.classList.add("dark");else el.classList.remove("dark");el.style.colorScheme=r;}catch(e){}})();</script>
  @vite([...])
  @inertiaHead
</head>
```

La versión minificada anterior es equivalente a llamar `preventFlashScript()` en el servidor — ambas producen el mismo IIFE.

### 4. Añadir el toggle

```tsx
import { ThemeToggle } from '@arqel-dev/theme';

export function Header() {
  return (
    <header>
      <h1>Dashboard</h1>
      <ThemeToggle className="rounded-md p-2 hover:bg-[var(--arqel-color-bg-muted)]" />
    </header>
  );
}
```

Listo. El toggle cicla `system → light → dark` y persiste en localStorage.

## Tokens disponibles

Todos los tokens se definen en `:root` (light) y se sobrescriben en `.dark`. Lista canónica:

### Surface (fondos)

| Token | Light | Dark |
| --- | --- | --- |
| `--arqel-color-bg` | `#ffffff` | `#0a0a0a` |
| `--arqel-color-bg-muted` | `#f5f5f5` | `#171717` |
| `--arqel-color-bg-subtle` | `#fafafa` | `#1f1f1f` |

### Foreground (texto)

| Token | Light | Dark |
| --- | --- | --- |
| `--arqel-color-fg` | `#0a0a0a` | `#fafafa` |
| `--arqel-color-fg-muted` | `#525252` | `#a3a3a3` |
| `--arqel-color-fg-subtle` | `#737373` | `#737373` |

### Borders

| Token | Light | Dark |
| --- | --- | --- |
| `--arqel-color-border` | `#e5e5e5` | `#262626` |
| `--arqel-color-border-strong` | `#d4d4d4` | `#404040` |

### Semánticos

| Token | Light | Dark |
| --- | --- | --- |
| `--arqel-color-primary` | `#6366f1` | `#818cf8` |
| `--arqel-color-success` | `#10b981` | `#34d399` |
| `--arqel-color-warning` | `#f59e0b` | `#fbbf24` |
| `--arqel-color-danger` | `#ef4444` | `#f87171` |
| `--arqel-color-info` | `#0ea5e9` | `#38bdf8` |

Cada color "semántico" tiene un compañero `*-fg` para el texto contrastante (e.g. `--arqel-color-primary-fg`).

## Crear un tema personalizado

### Override simple

Sobrescribe variables en tu CSS después de importar `tokens.css`:

```css
/* resources/css/app.css */
@import 'tailwindcss';
@import '@arqel-dev/theme/tokens.css';

:root {
  --arqel-color-primary: #ff6b35;        /* naranja */
  --arqel-color-primary-fg: #ffffff;
  --arqel-color-primary-hover: #e55a2b;
}

.dark {
  --arqel-color-primary: #ffa07a;
  --arqel-color-primary-fg: #1a1a1a;
  --arqel-color-primary-hover: #ff8c5a;
}
```

Listo — cada componente de Arqel que usa `var(--arqel-color-primary)` se actualiza automáticamente.

### Tema corporativo completo

Para una identidad visual totalmente personalizada (e.g. un tema "Forest Green Petshop"):

```css
:root {
  /* Surface — papel reciclado */
  --arqel-color-bg: #fdfbf7;
  --arqel-color-bg-muted: #f5f0e6;
  --arqel-color-bg-subtle: #faf6ec;

  /* Foreground — verde oscuro */
  --arqel-color-fg: #1f2e1f;
  --arqel-color-fg-muted: #4a5d4a;

  /* Brand — verde bosque */
  --arqel-color-primary: #2d5f3f;
  --arqel-color-primary-fg: #ffffff;
  --arqel-color-primary-hover: #1f4630;

  /* Borders */
  --arqel-color-border: #d4cfc1;

  /* Focus ring que combina con el primary */
  --arqel-color-ring: #2d5f3f;
}

.dark {
  --arqel-color-bg: #0f1a0f;
  --arqel-color-fg: #e8f0e8;
  --arqel-color-primary: #6db58a;
  --arqel-color-primary-fg: #0a1a0e;
  --arqel-color-border: #2a3a2a;
}
```

## Integración con Tailwind v4

Tailwind v4 funciona nativamente con variables CSS. Para usar tokens de Arqel vía clases de utilidad:

```css
@import 'tailwindcss';
@import '@arqel-dev/theme/tokens.css';

@theme {
  --color-bg: var(--arqel-color-bg);
  --color-fg: var(--arqel-color-fg);
  --color-primary: var(--arqel-color-primary);
  --color-primary-foreground: var(--arqel-color-primary-fg);
  --color-muted: var(--arqel-color-bg-muted);
  --color-border: var(--arqel-color-border);
}
```

Ahora `bg-primary`, `text-fg`, `border-border` funcionan y responden automáticamente a dark-mode.

## Hook `useTheme`

Para componentes que necesitan reaccionar programáticamente al tema:

```tsx
import { useTheme } from '@arqel-dev/theme';

function ChartWidget() {
  const { theme, resolvedTheme, setTheme } = useTheme();

  // Pasa el color concreto a libs que no leen CSS vars (Recharts, etc.)
  const lineColor = resolvedTheme === 'dark' ? '#818cf8' : '#6366f1';

  return (
    <div>
      <p>Current theme: {theme} (resolved: {resolvedTheme})</p>
      <button onClick={() => setTheme('light')}>Force light</button>
      <Chart strokeColor={lineColor} />
    </div>
  );
}
```

La diferencia entre `theme` y `resolvedTheme`:

- `theme` — preferencia del usuario, puede ser `'system'`.
- `resolvedTheme` — siempre `'light'` o `'dark'`. Usa este para lógica concreta.

## Opt-out de dark-mode

Si quieres una app **solo en light**, usa `defaultTheme="light"` y omite el toggle:

```tsx
<ThemeProvider defaultTheme="light">
  {/* sin ThemeToggle */}
</ThemeProvider>
```

Como `defaultTheme` solo se usa cuando localStorage está vacío, los usuarios pueden seguir teniendo `dark` guardado de otra app del mismo dominio. Para forzar light de manera absoluta:

```tsx
import { useEffect } from 'react';
import { useTheme } from '@arqel-dev/theme';

function ForceLight() {
  const { setTheme } = useTheme();
  useEffect(() => setTheme('light'), [setTheme]);
  return null;
}
```

Renderiza `<ForceLight />` dentro del provider.

## Múltiples storage keys

Apps separadas en el mismo dominio (e.g. marketplace + admin) pueden querer preferencias independientes:

```tsx
<ThemeProvider storageKey="arqel-marketplace-theme">
  {/* marketplace */}
</ThemeProvider>

<ThemeProvider storageKey="arqel-admin-theme">
  {/* admin */}
</ThemeProvider>
```

Recuerda pasar la misma `storageKey` al snippet anti-FOUC de Blade — si no, la lectura inicial usará la key por defecto y puede divergir.

## API completa

| Export | Tipo | Descripción |
| --- | --- | --- |
| `ThemeProvider` | Componente | Context provider |
| `ThemeToggle` | Componente | Botón cíclico |
| `useTheme` | Hook | `{ theme, resolvedTheme, setTheme }` |
| `preventFlashScript` | Función | Devuelve string IIFE para `<script>` |
| `Theme` | Tipo | `'light' \| 'dark' \| 'system'` |
| `ResolvedTheme` | Tipo | `'light' \| 'dark'` |
| `getSystemTheme` | Función | Lee `prefers-color-scheme` |
| `readStoredTheme` | Función | Lee localStorage (SSR-safe) |
| `writeStoredTheme` | Función | Escribe localStorage |

## Ver también

- `SKILL.md` para `@arqel-dev/theme` — referencia rápida del contrato
- `apps/docs/guide/getting-started.md` — setup inicial de Arqel
- ADR-001 — Inertia-only (theming no usa fetch)
