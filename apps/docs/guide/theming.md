# Theming

> Sistema de temas do Arqel — dark-mode, tokens semânticos e customização visual completa do seu painel.

O Arqel oferece um sistema de theming completo via o pacote **`@arqel/theme`**. Ele combina três peças:

1. **Tokens semânticos via CSS variables** — descrevem intenção (`bg`, `fg`, `primary`), não cores cruas.
2. **`<ThemeProvider>` React** — aplica `dark` no `<html>` baseado em preferência do utilizador + `prefers-color-scheme`.
3. **Snippet inline anti-FOUC** — evita flash branco antes do React montar.

Tudo funciona out-of-the-box num projeto Arqel novo. Personalizar é uma questão de sobrescrever variáveis CSS.

## Setup básico

### 1. Instalar

```bash
pnpm add @arqel/theme
```

### 2. Importar tokens + envolver app

No seu entry point Inertia:

```tsx
// resources/js/app.tsx
import { ThemeProvider } from '@arqel/theme';
import '@arqel/theme/tokens.css';
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

### 3. Adicionar snippet anti-FOUC no Blade

Sem isto, usuários em modo escuro veem flash branco antes do React montar:

```blade
{{-- resources/views/app.blade.php --}}
<head>
  <script>(function(){try{var k="arqel-theme",t=null;try{t=localStorage.getItem(k)}catch(e){}if(t!=="light"&&t!=="dark"&&t!=="system")t="system";var r=t==="system"?(window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"):t;var el=document.documentElement;if(r==="dark")el.classList.add("dark");else el.classList.remove("dark");el.style.colorScheme=r;}catch(e){}})();</script>
  @vite([...])
  @inertiaHead
</head>
```

A versão minificada acima é equivalente a chamar `preventFlashScript()` no servidor — ambos produzem o mesmo IIFE.

### 4. Adicionar o toggle

```tsx
import { ThemeToggle } from '@arqel/theme';

export function Header() {
  return (
    <header>
      <h1>Dashboard</h1>
      <ThemeToggle className="rounded-md p-2 hover:bg-[var(--arqel-color-bg-muted)]" />
    </header>
  );
}
```

Pronto. Toggle cicla `system → light → dark` e persiste em localStorage.

## Tokens disponíveis

Todos os tokens são definidos em `:root` (light) e sobrescritos em `.dark`. Lista canônica:

### Surface (fundos)

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

### Semantic

| Token | Light | Dark |
| --- | --- | --- |
| `--arqel-color-primary` | `#6366f1` | `#818cf8` |
| `--arqel-color-success` | `#10b981` | `#34d399` |
| `--arqel-color-warning` | `#f59e0b` | `#fbbf24` |
| `--arqel-color-danger` | `#ef4444` | `#f87171` |
| `--arqel-color-info` | `#0ea5e9` | `#38bdf8` |

Cada cor "semantic" tem um par `*-fg` para texto contrastante (ex.: `--arqel-color-primary-fg`).

## Criando um tema custom

### Override simples

Sobrescreva variáveis no seu CSS após importar `tokens.css`:

```css
/* resources/css/app.css */
@import 'tailwindcss';
@import '@arqel/theme/tokens.css';

:root {
  --arqel-color-primary: #ff6b35;        /* laranja */
  --arqel-color-primary-fg: #ffffff;
  --arqel-color-primary-hover: #e55a2b;
}

.dark {
  --arqel-color-primary: #ffa07a;
  --arqel-color-primary-fg: #1a1a1a;
  --arqel-color-primary-hover: #ff8c5a;
}
```

Pronto — todos os componentes Arqel que usam `var(--arqel-color-primary)` se atualizam automaticamente.

### Tema corporativo completo

Para uma identidade visual completamente custom (ex.: tema "Petshop Verde"):

```css
:root {
  /* Surface — papel reciclado */
  --arqel-color-bg: #fdfbf7;
  --arqel-color-bg-muted: #f5f0e6;
  --arqel-color-bg-subtle: #faf6ec;

  /* Foreground — verde escuro */
  --arqel-color-fg: #1f2e1f;
  --arqel-color-fg-muted: #4a5d4a;

  /* Brand — verde floresta */
  --arqel-color-primary: #2d5f3f;
  --arqel-color-primary-fg: #ffffff;
  --arqel-color-primary-hover: #1f4630;

  /* Borders */
  --arqel-color-border: #d4cfc1;

  /* Focus ring igual ao primary */
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

## Integração com Tailwind v4

Tailwind v4 trabalha nativamente com CSS variables. Para usar tokens Arqel via classes utilitárias:

```css
@import 'tailwindcss';
@import '@arqel/theme/tokens.css';

@theme {
  --color-bg: var(--arqel-color-bg);
  --color-fg: var(--arqel-color-fg);
  --color-primary: var(--arqel-color-primary);
  --color-primary-foreground: var(--arqel-color-primary-fg);
  --color-muted: var(--arqel-color-bg-muted);
  --color-border: var(--arqel-color-border);
}
```

Agora `bg-primary`, `text-fg`, `border-border` funcionam e respondem automaticamente ao dark-mode.

## Hook `useTheme`

Para componentes que precisam reagir programaticamente ao tema:

```tsx
import { useTheme } from '@arqel/theme';

function ChartWidget() {
  const { theme, resolvedTheme, setTheme } = useTheme();

  // Passar cor concreta para libs que não leem CSS vars (Recharts, etc.)
  const lineColor = resolvedTheme === 'dark' ? '#818cf8' : '#6366f1';

  return (
    <div>
      <p>Tema atual: {theme} (resolvido: {resolvedTheme})</p>
      <button onClick={() => setTheme('light')}>Forçar claro</button>
      <Chart strokeColor={lineColor} />
    </div>
  );
}
```

A diferença entre `theme` e `resolvedTheme`:

- `theme` — preferência do utilizador, pode ser `'system'`.
- `resolvedTheme` — sempre `'light'` ou `'dark'`. Use este para lógica concreta.

## Dark-mode opt-out

Se você quer um app **só** em modo claro, use `defaultTheme="light"` e omita o toggle:

```tsx
<ThemeProvider defaultTheme="light">
  {/* sem ThemeToggle */}
</ThemeProvider>
```

Como `defaultTheme` só é usado quando localStorage está vazio, ainda assim é possível usuários terem `dark` armazenado de outra app no mesmo domínio. Para forçar light absolutamente:

```tsx
import { useEffect } from 'react';
import { useTheme } from '@arqel/theme';

function ForceLight() {
  const { setTheme } = useTheme();
  useEffect(() => setTheme('light'), [setTheme]);
  return null;
}
```

Renderize `<ForceLight />` dentro do provider.

## Múltiplas chaves de storage

Apps separados no mesmo domínio (ex.: marketplace + admin) podem querer preferências independentes:

```tsx
<ThemeProvider storageKey="arqel-marketplace-theme">
  {/* marketplace */}
</ThemeProvider>

<ThemeProvider storageKey="arqel-admin-theme">
  {/* admin */}
</ThemeProvider>
```

Lembre de passar o mesmo `storageKey` no snippet anti-FOUC do Blade — caso contrário a leitura inicial usará a chave default e poderá divergir.

## API completa

| Export | Tipo | Descrição |
| --- | --- | --- |
| `ThemeProvider` | Component | Context provider |
| `ThemeToggle` | Component | Botão cycle |
| `useTheme` | Hook | `{ theme, resolvedTheme, setTheme }` |
| `preventFlashScript` | Function | Retorna IIFE string para `<script>` |
| `Theme` | Type | `'light' \| 'dark' \| 'system'` |
| `ResolvedTheme` | Type | `'light' \| 'dark'` |
| `getSystemTheme` | Function | Lê `prefers-color-scheme` |
| `readStoredTheme` | Function | Lê localStorage (SSR-safe) |
| `writeStoredTheme` | Function | Escreve localStorage |

## Ver também

- `SKILL.md` do `@arqel/theme` — referência rápida de contratos
- `apps/docs/guide/getting-started.md` — setup inicial do Arqel
- ADR-001 — Inertia-only (theming não usa fetch)
