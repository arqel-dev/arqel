# SKILL.md — arqel/theme

## Purpose

Pacote responsável por **dark-mode**, **tokens de design semânticos** e **theming runtime** para painéis Arqel.

Fornece:

- `tokens.css` — variáveis CSS canônicas (`--arqel-color-bg`, `--arqel-color-fg`, `--arqel-color-primary`, etc.) em `:root` (light) e `.dark` (dark).
- `<ThemeProvider>` — Context React que aplica/remove a classe `dark` no `<html>` baseado em preferência do utilizador + `prefers-color-scheme`.
- `<ThemeToggle>` — botão acessível que cicla `system → light → dark`.
- `useTheme()` — hook para ler/atualizar tema em qualquer componente.
- `preventFlashScript()` — snippet inline para evitar FOUC (flash branco antes do React montar).

## Key Contracts

### `Theme` (type)

```ts
type Theme = 'light' | 'dark' | 'system';
type ResolvedTheme = 'light' | 'dark';
```

`system` segue `prefers-color-scheme` do SO. `resolvedTheme` é sempre concreto.

### `<ThemeProvider>`

```tsx
<ThemeProvider defaultTheme="system" storageKey="arqel-theme" darkClass="dark">
  {children}
</ThemeProvider>
```

Props:

| Prop | Default | Descrição |
| --- | --- | --- |
| `defaultTheme` | `'system'` | Tema usado quando nada está em localStorage |
| `storageKey` | `'arqel-theme'` | Chave em localStorage |
| `darkClass` | `'dark'` | Classe aplicada em `<html>` quando dark |
| `attribute` | `'class'` | `'class'` ou `'data-theme'` |

### `useTheme()`

```ts
const { theme, resolvedTheme, setTheme } = useTheme();
```

Lança erro se chamado fora de `<ThemeProvider>` (fail-fast).

### `preventFlashScript(options?)`

Retorna string IIFE para inserir antes dos bundles React:

```blade
<script>{!! \Arqel\Theme\preventFlashScript() !!}</script>
```

## Conventions

1. **Tokens semânticos, nunca cores cruas** — componentes Arqel usam `var(--arqel-color-bg)`, nunca `#ffffff`.
2. **localStorage opt-out** — falhas de `localStorage.setItem/getItem` são silenciadas (Safari private mode, iframes).
3. **SSR-safe** — todas funções verificam `typeof window === 'undefined'`.
4. **Sem dependências runtime extras** — apenas `react` (peer).
5. **Class-based dark mode** (Tailwind v4 syntax: `@variant dark (&:where(.dark, .dark *))`).

## Examples

### Setup mínimo num app Inertia

```tsx
// resources/js/app.tsx
import { ThemeProvider } from '@arqel/theme';
import '@arqel/theme/tokens.css';

createInertiaApp({
  setup({ el, App, props }) {
    createRoot(el).render(
      <ThemeProvider>
        <App {...props} />
      </ThemeProvider>,
    );
  },
});
```

### Toggle no header

```tsx
import { ThemeToggle } from '@arqel/theme';

<header className="flex items-center justify-between">
  <h1>Dashboard</h1>
  <ThemeToggle className="rounded-md p-2 hover:bg-muted" />
</header>
```

### FOUC prevention (Blade)

```blade
<head>
  {{-- ... --}}
  <script>(function(){try{var k="arqel-theme",t=null;try{t=localStorage.getItem(k)}catch(e){}if(t!=="light"&&t!=="dark"&&t!=="system")t="system";var r=t==="system"?(window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"):t;if(r==="dark")document.documentElement.classList.add("dark");document.documentElement.style.colorScheme=r;}catch(e){}})();</script>
  @vite([...])
</head>
```

### Override de tema (custom branding)

```css
:root {
  --arqel-color-primary: #ff6b35;
  --arqel-color-primary-fg: #ffffff;
}
.dark {
  --arqel-color-primary: #ffa07a;
}
```

## Anti-patterns

- ❌ Usar `<ThemeToggle>` fora de `<ThemeProvider>` — lança erro em runtime.
- ❌ Hard-coding `bg-white dark:bg-neutral-900` em components quando há token semântico equivalente. Prefira `bg-[var(--arqel-color-bg)]`.
- ❌ Múltiplos `<ThemeProvider>` aninhados — o mais interno vence, mas confunde devtools.
- ❌ Esquecer o snippet `preventFlashScript` no Blade — usuários verão flash branco em dark mode.
- ❌ Usar `window` ou `document` no top-level de componentes — quebra SSR.

## Related

- ADR-001 — Inertia-only (theme não usa fetch)
- ADR-016 — Sem libs de fetch para CRUD
- `@arqel/ui` — consome tokens via Tailwind v4 `@theme`
- `apps/docs/guide/theming.md` — guia completo de theming
