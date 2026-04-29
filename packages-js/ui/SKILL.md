# SKILL.md — @arqel/ui

> Contexto canónico para AI agents.

## Purpose

`@arqel/ui` consolida componentes estruturais (AppShell, DataTable, FormRenderer, ActionButton, FlashContainer) que orquestram a UI do admin panel. Componentes atômicos (Input, Select, Dialog) vêm via Base UI + ShadCN CLI; este pacote é a "casca" que monta tudo.

## Status

**Entregue (UI-001 + UI-002):**

- Esqueleto do pacote com 9 entry points subpath
- `globals.css` com Tailwind v4 `@import` + design tokens (oklch) + `.dark` override
- `cn(...inputs)` utility (clsx + tailwind-merge)
- `<Button>` primitive com cva variants (`default | outline | ghost | destructive`) e sizes (`sm | md | lg | icon`) — base para ActionButton
- `<CanAccess>` wrapper sobre `useCanAccess`
- `<AppShell variant>`: 4 variants (`sidebar-left | sidebar-right | topbar-only | full-width`) com slots `sidebar | topbar | footer | children`
- `<Sidebar>`: rail desktop fixo (`--sidebar-width`, default 240px) + overlay mobile via Base UI Dialog quando `open`/`onOpenChange` fornecidos. Items vêm de `useNavigation()` ou via prop. Suporta groups, badges, `aria-current="page"`, focus ring
- `<Topbar>`: brand slot + theme toggle (`useTheme`) + mobile menu trigger (`onMobileMenuClick`) + slots `search | userMenu | tenantSwitcher`
- `<MainContent>`: padding responsivo (`px-4 sm:px-6 lg:px-8`) + max-width configurável (`md..7xl | none`) + slots `breadcrumbs | header`
- `<Footer>`: faixa minimal abaixo do `<MainContent>`

**Por chegar (placeholder barrels):**

- UI-003: ResourceIndex + DataTable + TableFilters + TablePagination + TableToolbar
- UI-004: FormRenderer + FieldRenderer + FormSection + FormActions
- UI-005: ActionModal + ConfirmDialog + FlashContainer + FlashToast

## Key Contracts

```tsx
import '@arqel/ui/styles.css';
import { Button, CanAccess } from '@arqel/ui';

<CanAccess ability="users.create">
  <Button variant="default">Create user</Button>
</CanAccess>
```

```ts
import { cn } from '@arqel/ui/utils';

cn('px-2 py-1', condition && 'bg-red-500'); // tailwind-merge dedup
```

## Conventions

- Subpath imports (`@arqel/ui/action`, `@arqel/ui/auth`) preferidos para tree-shaking
- CSS exposto como `@arqel/ui/styles.css` — apps importam uma vez no entry
- Design tokens via CSS vars (`--color-arqel-*`, `--radius-arqel-*`) — temáveis sem rebuild
- `.dark` class flip aplicada por `<ThemeProvider>` de `@arqel/react`
- Componentes usam `forwardRef` quando expõem elementos DOM
- Variants via `class-variance-authority` (cva)
- Apenas peerDependencies para Base UI / TanStack Table / lucide-react / @arqel/react / @arqel/hooks — apps escolhem versões

## Anti-patterns

- ❌ **Hardcode de cor** — usa CSS vars para honrar tema
- ❌ **Bundle Tailwind** — apps trazem o próprio Tailwind; expomos só os tokens
- ❌ **Importar de `dist/`** — usa exports declarados (`@arqel/ui` ou subpaths)
- ❌ **Reinventar `cn`** — sempre `import { cn } from '@arqel/ui/utils'`

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §UI-001..010
- API: [`PLANNING/06-api-react.md`](../../PLANNING/06-api-react.md) §8, §12
- Source: [`packages-js/ui/src/`](src/)
- Tests: [`packages-js/ui/tests/`](tests/)
