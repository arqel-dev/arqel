# Theming

> Arqel's theming system — dark-mode, semantic tokens, and full visual customization of your panel.

::: tip Semantic tokens come from shadcn
After the migration to **shadcn UI (`new-york` preset) + Radix UI**, the canonical semantic tokens (`--background`, `--foreground`, `--primary`, `--border`, `--muted`, `--muted-foreground`, `--destructive`, `--ring`, `--radius`) are defined in `@arqel-dev/ui/styles.css` in the `:root` (light) and `.dark` (dark) blocks, with the `@theme inline` bridge for Tailwind v4 to expose `bg-background`, `text-foreground`, `border-border`, etc. utilities automatically.

The **`@arqel-dev/theme`** package still exists and only takes care of the **`<ThemeProvider>` React + toggle + anti-FOUC snippet**. The CSS variables below prefixed with `--arqel-color-*` are an **optional, legacy** layer — new projects should prefer the shadcn tokens (`--primary` instead of `--arqel-color-primary`, etc.).
:::

Arqel offers a complete theming system combining three pieces:

1. **shadcn semantic tokens** in `@arqel-dev/ui/styles.css` — describe intent (`background`, `foreground`, `primary`, `border`, `muted`, `destructive`, `ring`).
2. **`<ThemeProvider>` React** (from `@arqel-dev/theme`) — applies `dark` on `<html>` based on user preference + `prefers-color-scheme`.
3. **Inline anti-FOUC snippet** — avoids a white flash before React mounts.

Everything works out-of-the-box in a fresh Arqel project. Customizing is just a matter of overriding CSS variables.

## shadcn tokens (canonical)

```css
/* @arqel-dev/ui/styles.css — already imported by the arqel:install scaffold */
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
  /* ... bridge to Tailwind utilities */
}
```

To customize the brand, override `--primary` (and `--primary-foreground` for contrasting text) in your `app.css` **after** importing `@arqel-dev/ui/styles.css`.

## Basic setup

### 1. Install

```bash
pnpm add @arqel-dev/theme
```

### 2. Import tokens + wrap the app

In your Inertia entry point:

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

### 3. Add the anti-FOUC snippet to Blade

Without this, dark-mode users see a white flash before React mounts:

```blade
{{-- resources/views/app.blade.php --}}
<head>
  <script>(function(){try{var k="arqel-theme",t=null;try{t=localStorage.getItem(k)}catch(e){}if(t!=="light"&&t!=="dark"&&t!=="system")t="system";var r=t==="system"?(window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"):t;var el=document.documentElement;if(r==="dark")el.classList.add("dark");else el.classList.remove("dark");el.style.colorScheme=r;}catch(e){}})();</script>
  @vite([...])
  @inertiaHead
</head>
```

The minified version above is equivalent to calling `preventFlashScript()` on the server — both produce the same IIFE.

### 4. Add the toggle

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

Done. The toggle cycles `system → light → dark` and persists to localStorage.

## Available tokens

All tokens are defined in `:root` (light) and overridden in `.dark`. Canonical list:

### Surface (backgrounds)

| Token | Light | Dark |
| --- | --- | --- |
| `--arqel-color-bg` | `#ffffff` | `#0a0a0a` |
| `--arqel-color-bg-muted` | `#f5f5f5` | `#171717` |
| `--arqel-color-bg-subtle` | `#fafafa` | `#1f1f1f` |

### Foreground (text)

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

### Semantic

| Token | Light | Dark |
| --- | --- | --- |
| `--arqel-color-primary` | `#6366f1` | `#818cf8` |
| `--arqel-color-success` | `#10b981` | `#34d399` |
| `--arqel-color-warning` | `#f59e0b` | `#fbbf24` |
| `--arqel-color-danger` | `#ef4444` | `#f87171` |
| `--arqel-color-info` | `#0ea5e9` | `#38bdf8` |

Each "semantic" color has a `*-fg` companion for contrasting text (e.g. `--arqel-color-primary-fg`).

## Creating a custom theme

### Simple override

Override variables in your CSS after importing `tokens.css`:

```css
/* resources/css/app.css */
@import 'tailwindcss';
@import '@arqel-dev/theme/tokens.css';

:root {
  --arqel-color-primary: #ff6b35;        /* orange */
  --arqel-color-primary-fg: #ffffff;
  --arqel-color-primary-hover: #e55a2b;
}

.dark {
  --arqel-color-primary: #ffa07a;
  --arqel-color-primary-fg: #1a1a1a;
  --arqel-color-primary-hover: #ff8c5a;
}
```

Done — every Arqel component using `var(--arqel-color-primary)` updates automatically.

### Full corporate theme

For a fully custom visual identity (e.g. a "Forest Green Petshop" theme):

```css
:root {
  /* Surface — recycled paper */
  --arqel-color-bg: #fdfbf7;
  --arqel-color-bg-muted: #f5f0e6;
  --arqel-color-bg-subtle: #faf6ec;

  /* Foreground — dark green */
  --arqel-color-fg: #1f2e1f;
  --arqel-color-fg-muted: #4a5d4a;

  /* Brand — forest green */
  --arqel-color-primary: #2d5f3f;
  --arqel-color-primary-fg: #ffffff;
  --arqel-color-primary-hover: #1f4630;

  /* Borders */
  --arqel-color-border: #d4cfc1;

  /* Focus ring matching primary */
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

## Tailwind v4 integration

Tailwind v4 works natively with CSS variables. To use Arqel tokens via utility classes:

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

Now `bg-primary`, `text-fg`, `border-border` work and respond automatically to dark-mode.

## `useTheme` hook

For components that need to react programmatically to the theme:

```tsx
import { useTheme } from '@arqel-dev/theme';

function ChartWidget() {
  const { theme, resolvedTheme, setTheme } = useTheme();

  // Pass concrete color to libs that don't read CSS vars (Recharts, etc.)
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

The difference between `theme` and `resolvedTheme`:

- `theme` — user preference, may be `'system'`.
- `resolvedTheme` — always `'light'` or `'dark'`. Use this for concrete logic.

## Dark-mode opt-out

If you want a **light-only** app, use `defaultTheme="light"` and omit the toggle:

```tsx
<ThemeProvider defaultTheme="light">
  {/* no ThemeToggle */}
</ThemeProvider>
```

Since `defaultTheme` is only used when localStorage is empty, users may still have `dark` stored from another app on the same domain. To force light absolutely:

```tsx
import { useEffect } from 'react';
import { useTheme } from '@arqel-dev/theme';

function ForceLight() {
  const { setTheme } = useTheme();
  useEffect(() => setTheme('light'), [setTheme]);
  return null;
}
```

Render `<ForceLight />` inside the provider.

## Multiple storage keys

Separate apps on the same domain (e.g. marketplace + admin) may want independent preferences:

```tsx
<ThemeProvider storageKey="arqel-marketplace-theme">
  {/* marketplace */}
</ThemeProvider>

<ThemeProvider storageKey="arqel-admin-theme">
  {/* admin */}
</ThemeProvider>
```

Remember to pass the same `storageKey` to the Blade anti-FOUC snippet — otherwise the initial read will use the default key and may diverge.

## Full API

| Export | Type | Description |
| --- | --- | --- |
| `ThemeProvider` | Component | Context provider |
| `ThemeToggle` | Component | Cycle button |
| `useTheme` | Hook | `{ theme, resolvedTheme, setTheme }` |
| `preventFlashScript` | Function | Returns IIFE string for `<script>` |
| `Theme` | Type | `'light' \| 'dark' \| 'system'` |
| `ResolvedTheme` | Type | `'light' \| 'dark'` |
| `getSystemTheme` | Function | Reads `prefers-color-scheme` |
| `readStoredTheme` | Function | Reads localStorage (SSR-safe) |
| `writeStoredTheme` | Function | Writes localStorage |

## See also

- `SKILL.md` for `@arqel-dev/theme` — quick contract reference
- `apps/docs/guide/getting-started.md` — initial Arqel setup
- ADR-001 — Inertia-only (theming does not use fetch)
