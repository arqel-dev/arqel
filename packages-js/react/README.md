# @arqel-dev/react

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![React](https://img.shields.io/badge/react-%5E19-61dafb.svg)](https://react.dev)
[![Status](https://img.shields.io/badge/status-pre--alpha-orange.svg)](#)

React + Inertia bindings, providers, and utilities for [Arqel](https://arqel.dev).

## Status

🚧 **Pre-alpha** — REACT-001..004 entregues.

## Install

```bash
pnpm add @arqel-dev/react @arqel-dev/types
pnpm add @inertiajs/react react react-dom
```

## Usage

```ts
// resources/js/app.tsx
import { createArqelApp } from '@arqel-dev/react/inertia';

const userPages = import.meta.glob('./Pages/**/*.tsx');

createArqelApp({
  appName: 'Acme Admin',
  pages: userPages,
});
```

```tsx
// Inside any component
import { usePanel, useTheme } from '@arqel-dev/react';
import { useTranslator, route } from '@arqel-dev/react/utils';

const panel = usePanel();
const { theme, toggle } = useTheme();
```

## Convenções

- Subpath imports preferidos: `@arqel-dev/react/inertia`, `@arqel-dev/react/providers`, `@arqel-dev/react/context`, `@arqel-dev/react/utils`
- `peerDependencies` para React 19 + Inertia 2 (não bundla)
- SSR-safe: hydrateRoot quando há markup, createRoot caso contrário

## Links

- [Documentação](https://arqel.dev/docs/react) — em construção
- [PLANNING](../../PLANNING/08-fase-1-mvp.md) — tickets `REACT-*`
