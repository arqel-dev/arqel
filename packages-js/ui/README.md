# @arqel-dev/ui

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![React](https://img.shields.io/badge/react-%5E19-61dafb.svg)](https://react.dev)
[![Status](https://img.shields.io/badge/status-pre--alpha-orange.svg)](#)

Structural React components for [Arqel](https://arqel.dev) admin panels.

## Status

🚧 **Pre-alpha** — UI-001..006 entregues (shell, table, form, action, flash, utility).

## Install

```bash
pnpm add @arqel-dev/ui @arqel-dev/react @arqel-dev/hooks @arqel-dev/types
pnpm add @inertiajs/react react react-dom @base-ui-components/react @tanstack/react-table lucide-react
```

## Usage

```tsx
import '@arqel-dev/ui/styles.css';
import {
  AppShell, Sidebar, Topbar, MainContent,
  ResourceIndex, FormRenderer, ActionButton, FlashContainer,
} from '@arqel-dev/ui';
```

## What's inside

| Subpath           | Components                                                                    |
|-------------------|--------------------------------------------------------------------------------|
| `@arqel-dev/ui/shell`    | `AppShell`, `Sidebar`, `Topbar`, `MainContent`, `Footer`                       |
| `@arqel-dev/ui/table`    | `DataTable`, `TableCell`, `TableFilters`, `TablePagination`, `TableToolbar`    |
| `@arqel-dev/ui/resource` | `ResourceIndex`                                                                |
| `@arqel-dev/ui/form`     | `FormRenderer`, `FieldRenderer`, `FormSection`, `FormFieldset`, `FormGrid`, `FormTabs`, `FormActions`, `registerField` |
| `@arqel-dev/ui/action`   | `Button`, `ActionButton`, `ActionMenu`, `ConfirmDialog`, `ActionFormModal`     |
| `@arqel-dev/ui/auth`     | `CanAccess`                                                                    |
| `@arqel-dev/ui/flash`    | `FlashContainer`, `FlashToast`                                                 |
| `@arqel-dev/ui/utility`  | `Breadcrumbs`, `PageHeader`, `EmptyState`, `ErrorState`, `LoadingSkeleton`     |
| `@arqel-dev/ui/utils`    | `cn`                                                                           |

## Design tokens

Tokens são CSS vars em `oklch`, honram light/dark via `.dark` class flip aplicada pelo `<ThemeProvider>`:

```css
--color-arqel-bg / -fg
--color-arqel-primary / -fg
--color-arqel-secondary / -fg
--color-arqel-destructive / -fg
--color-arqel-success / -warning
--color-arqel-muted / -fg
--color-arqel-border / -input / -ring
--radius-arqel / -sm / -lg
```

## Links

- [Documentação](https://arqel.dev/docs/ui) — em construção
- [PLANNING](../../PLANNING/08-fase-1-mvp.md) — tickets `UI-*`
