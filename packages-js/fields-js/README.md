# @arqel-dev/fields

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![React](https://img.shields.io/badge/react-%5E19-61dafb.svg)](https://react.dev)
[![Status](https://img.shields.io/badge/status-pre--alpha-orange.svg)](#)

Rich React input components for [Arqel](https://arqel.dev), registered into `@arqel-dev/ui`'s FieldRegistry.

## Status

🚧 **Pre-alpha** — FIELDS-JS-001 + FIELDS-JS-002 entregues (9 components básicos: TextInput, TextareaInput, EmailInput, UrlInput, PasswordInput, NumberInput, CurrencyInput, Checkbox, Toggle).

## Install

```bash
pnpm add @arqel-dev/fields @arqel-dev/ui @arqel-dev/react @arqel-dev/types
```

## Usage

```tsx
// resources/js/app.tsx
import '@arqel-dev/ui/styles.css';
import '@arqel-dev/fields/register'; // side effect: registers all built-ins

import { createArqelApp } from '@arqel-dev/react/inertia';

createArqelApp({ appName: 'Acme', pages: import.meta.glob('./Pages/**/*.tsx') });
```

## Subpath imports

```ts
import { TextInput, EmailInput } from '@arqel-dev/fields/text';
import { NumberInput, CurrencyInput } from '@arqel-dev/fields/number';
import { Checkbox, Toggle } from '@arqel-dev/fields/boolean';
```

## Links

- [Documentação](https://arqel.dev/docs/fields-js) — em construção
- [PLANNING](../../PLANNING/08-fase-1-mvp.md) — tickets `FIELDS-JS-*`
