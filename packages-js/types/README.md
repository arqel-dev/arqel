# @arqel/types

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![TypeScript](https://img.shields.io/badge/typescript-%5E5.6-3178c6.svg)](https://www.typescriptlang.org)
[![Status](https://img.shields.io/badge/status-pre--alpha-orange.svg)](#)

Shared TypeScript types for the [Arqel](https://arqel.dev) ecosystem — fields, resources, tables, forms, actions, and Inertia shared props.

## Status

🚧 **Pre-alpha** — covers all serialiser shapes from the PHP packages.

## Install

```bash
pnpm add -D @arqel/types
```

## Usage

```ts
// Tree-shake-friendly subpath imports
import type { FieldSchema, isFieldType } from '@arqel/types/fields';
import type { ResourceIndexProps, RecordType } from '@arqel/types/resources';
import type { SharedProps } from '@arqel/types/inertia';

// Or barrel
import type { FieldSchema, ColumnSchema, FormSchema } from '@arqel/types';
```

## Convenções

- Zero runtime — só types + 4 type guards (`isFieldType`, `isFieldEntry`, `isLayoutEntry`, `resolveFieldEntry`)
- `sideEffects: false` para máximo tree-shaking
- Synced com `Arqel\Core\Support\FieldSchemaSerializer` PHP

## Links

- [Documentação](https://arqel.dev/docs/types) — em construção
- [PLANNING](../../PLANNING/08-fase-1-mvp.md) — tickets `TYPES-*`
