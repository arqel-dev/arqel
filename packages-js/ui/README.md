# @arqel/ui

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![React](https://img.shields.io/badge/react-%5E19-61dafb.svg)](https://react.dev)
[![Status](https://img.shields.io/badge/status-pre--alpha-orange.svg)](#)

Structural React components for [Arqel](https://arqel.dev) admin panels.

## Status

🚧 **Pre-alpha** — UI-001 entregue (esqueleto + Button + CanAccess + cn). UI-002..005 vêm a seguir.

## Install

```bash
pnpm add @arqel/ui @arqel/react @arqel/hooks @arqel/types
pnpm add @inertiajs/react react react-dom @base-ui-components/react @tanstack/react-table lucide-react
```

## Usage

```tsx
import '@arqel/ui/styles.css';
import { Button, CanAccess } from '@arqel/ui';

export function CreateUserButton() {
  return (
    <CanAccess ability="users.create">
      <Button>Create user</Button>
    </CanAccess>
  );
}
```

## Design tokens

Tokens são CSS vars em `oklch`, honram light/dark via `.dark` class flip aplicada pelo `<ThemeProvider>`:

```css
--color-arqel-bg
--color-arqel-fg
--color-arqel-primary / -fg
--color-arqel-secondary / -fg
--color-arqel-destructive / -fg
--color-arqel-muted / -fg
--color-arqel-border
--color-arqel-ring
--radius-arqel / -sm / -lg
```

## Subpath imports

```ts
import { Button } from '@arqel/ui/action';
import { CanAccess } from '@arqel/ui/auth';
import { cn } from '@arqel/ui/utils';
```

## Links

- [Documentação](https://arqel.dev/docs/ui) — em construção
- [PLANNING](../../PLANNING/08-fase-1-mvp.md) — tickets `UI-*`
