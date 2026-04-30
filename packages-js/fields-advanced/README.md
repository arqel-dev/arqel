# @arqel/fields-advanced

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![React](https://img.shields.io/badge/react-%5E19-61dafb.svg)](https://react.dev)
[![Status](https://img.shields.io/badge/status-pre--alpha-orange.svg)](#)

Advanced React input components for [Arqel](https://arqel.dev) — RichText, Markdown, Code, Repeater, Builder, KeyValue, Tags, Wizard. Sibling do pacote PHP `arqel/fields-advanced`: o PHP define os types/setters; este pacote JS provê o render layer.

## Status

🚧 **Pre-alpha** — FIELDS-ADV-018 (scoped) entregue: skeleton do pacote + lazy registry para 8 component slots. Cada slot ainda renderiza um placeholder; as implementações concretas chegam em FIELDS-ADV-010..017.

## Install

```bash
pnpm add @arqel/fields-advanced @arqel/ui @arqel/types
```

## Usage

```tsx
// resources/js/app.tsx
import '@arqel/ui/styles.css';
import '@arqel/fields/register';            // built-ins (text, number, ...)
import '@arqel/fields-advanced/register';   // side effect: lazy register dos 8 slots

import { createArqelApp } from '@arqel/react/inertia';

createArqelApp({ appName: 'Acme', pages: import.meta.glob('./Pages/**/*.tsx') });
```

Cada `registerField()` envolve um `import()` dinâmico em `React.lazy`, então o chunk só é carregado quando o field correspondente renderizar pela primeira vez (Suspense boundary required no app shell — ver `@arqel/ui`).

## Subpath imports

```ts
import { RichTextInput } from '@arqel/fields-advanced/rich-text';
import { MarkdownInput } from '@arqel/fields-advanced/markdown';
import { CodeInput }     from '@arqel/fields-advanced/code';
import { RepeaterInput } from '@arqel/fields-advanced/repeater';
import { BuilderInput }  from '@arqel/fields-advanced/builder';
import { KeyValueInput } from '@arqel/fields-advanced/key-value';
import { TagsInput }     from '@arqel/fields-advanced/tags';
import { WizardInput }   from '@arqel/fields-advanced/wizard';
```

## Links

- [Documentação](https://arqel.dev/docs/fields-advanced) — em construção
- [PLANNING](../../PLANNING/09-fase-2-essenciais.md) — tickets `FIELDS-ADV-*`
