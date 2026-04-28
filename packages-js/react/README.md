# @arqel/react

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![React](https://img.shields.io/badge/react-%5E19-61dafb.svg)](https://react.dev)
[![Status](https://img.shields.io/badge/status-pre--alpha-orange.svg)](#)

React + Inertia bindings, providers, and utilities for [Arqel](https://arqel.dev).

## Status

🚧 **Pre-alpha** — REACT-001..004 entregues.

## Install

```bash
pnpm add @arqel/react @arqel/types
pnpm add @inertiajs/react react react-dom
```

## Usage

```ts
// resources/js/app.tsx
import { createArqelApp } from '@arqel/react/inertia';

const userPages = import.meta.glob('./Pages/**/*.tsx');

createArqelApp({
  appName: 'Acme Admin',
  pages: userPages,
});
```

```tsx
// Inside any component
import { usePanel, useTheme } from '@arqel/react';
import { useTranslator, route } from '@arqel/react/utils';

const panel = usePanel();
const { theme, toggle } = useTheme();
```

## Convenções

- Subpath imports preferidos: `@arqel/react/inertia`, `@arqel/react/providers`, `@arqel/react/context`, `@arqel/react/utils`
- `peerDependencies` para React 19 + Inertia 2 (não bundla)
- SSR-safe: hydrateRoot quando há markup, createRoot caso contrário

## Links

- [Documentação](https://arqel.dev/docs/react) — em construção
- [PLANNING](../../PLANNING/08-fase-1-mvp.md) — tickets `REACT-*`
