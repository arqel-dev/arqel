# SKILL.md — @arqel-dev/fields-advanced (JS)

> Contexto canónico para AI agents.

## Purpose

`@arqel-dev/fields-advanced` é o pacote React que entrega os 8 inputs avançados do Arqel: `RichTextInput`, `MarkdownInput`, `CodeInput`, `RepeaterInput`, `BuilderInput`, `KeyValueInput`, `TagsInput`, `WizardInput`. Existe ao lado do PHP `arqel-dev/fields-advanced` — o PHP define `Field::component()` (os mesmos 8 nomes em PascalCase) e o JS faz o render. A separação versus `@arqel-dev/fields` (core) existe porque os componentes ricos arrastam dependências pesadas (Tiptap, CodeMirror, Shiki, dnd-kit) que só devem entrar no bundle quando o panel realmente os usa.

## Status

**Setup (FIELDS-ADV-018, scoped):**

- Esqueleto do pacote `@arqel-dev/fields-advanced` com 10 entry points subpath (`./`, `./register`, `./rich-text`, `./markdown`, `./code`, `./repeater`, `./builder`, `./key-value`, `./tags`, `./wizard`)
- `src/register.ts` registra os 8 slots no `FieldRegistry` de `@arqel-dev/ui` envolvendo cada `import()` dinâmico em `React.lazy`, garantindo code-splitting on-demand sem alterar `@arqel-dev/ui`
- 8 stubs `<Name>Input.tsx` renderizam um placeholder `<div class="rounded border border-dashed ...">{name} not yet implemented (TICKET)</div>` + `<input type="hidden" id={inputId} value={JSON.stringify(value ?? null)} />` para preservar o round-trip do valor no submit do form enquanto o componente real não chegou
- `src/shared/PlaceholderInput.tsx` centraliza a UI do stub; cada componente é um wrapper trivial
- `src/shared/types.ts` re-exporta `FieldRendererProps` de `@arqel-dev/ui/form` para imports ergonômicos
- 1 teste Vitest valida que `import('../src/register.js')` não lança e que os 8 nomes ficam disponíveis em `getFieldComponent()`

### Coverage

- Total: 81 testes Vitest passando (smoke do `register.ts` cobre os 8 slots + dynamic-import individual de cada módulo lazy)

**Por chegar (FIELDS-ADV-010..017):**

- `RichTextInput` real (Tiptap + extensões)
- `MarkdownInput` (CodeMirror + remark/rehype preview)
- `CodeInput` (CodeMirror + Shiki highlight)
- `RepeaterInput` / `BuilderInput` (dnd-kit reorder)
- `KeyValueInput`, `TagsInput`
- `WizardInput` (multi-step + `<Activity>`)

## Key Contracts

```tsx
// resources/js/app.tsx
import '@arqel-dev/ui/styles.css';
import '@arqel-dev/fields/register';            // built-ins
import '@arqel-dev/fields-advanced/register';   // 8 slots advanced (lazy)

import { createArqelApp } from '@arqel-dev/react/inertia';

createArqelApp({ appName: 'Acme', pages: import.meta.glob('./Pages/**/*.tsx') });
```

A partir desse import, `<FieldRenderer>` resolve `field.component === 'RichTextInput'` (etc.) para o `LazyExoticComponent` registrado. O Suspense boundary do app shell de `@arqel-dev/ui` cobre a primeira render enquanto o chunk carrega.

```tsx
// Override custom: registre depois do side-effect import
import { registerField } from '@arqel-dev/ui/form';
import { MyTiptapInput } from './fields/MyTiptapInput';

registerField('RichTextInput', MyTiptapInput);
```

## Conventions

- **Nome do componente** segue `Field::component()` no PHP (PascalCase) — `RichTextInput`, `MarkdownInput`, `CodeInput`, `RepeaterInput`, `BuilderInput`, `KeyValueInput`, `TagsInput`, `WizardInput`
- **Lazy registration** via `React.lazy(() => import(...).then(m => ({ default: m.X })))` — preserva tree-shaking e gera um chunk por field type
- **Props canônicas** vêm de `FieldRendererProps` (re-exportado de `./shared/types.js`)
- **Side-effect entry**: `register.ts` é o único arquivo com `sideEffects: true` no `package.json`
- **Stubs** sempre emitem `<input type="hidden" id={inputId}>` com o valor serializado para não quebrar submits durante o período de transição

## Examples

```tsx
// PHP-side: src/Fields/RichTextField.php → component='RichTextInput'
// JS-side: registrado lazy aqui em register.ts; FieldRenderer descobre e renderiza
```

## Anti-patterns

- ❌ **Importar `register.ts` mais de uma vez** — registra 2x o mesmo slot; para override, chame `registerField` direto após o side-effect
- ❌ **Substituir `React.lazy` por import síncrono no register** — derrota o code-splitting que é a razão de existir do pacote
- ❌ **Adicionar Tiptap/CodeMirror/Shiki/dnd-kit como dep do pacote raiz** — cada componente concreto (FIELDS-ADV-010..017) declara suas próprias deps no commit que entrega o real
- ❌ **Renderizar `<label>` dentro do componente** — `<FieldRenderer>` é dono do label

## Related

- Tickets: [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §FIELDS-ADV-001..018
- PHP sibling: [`packages/fields-advanced/SKILL.md`](../../packages/fields-advanced/SKILL.md)
- API: [`PLANNING/06-api-react.md`](../../PLANNING/06-api-react.md)
- Source: [`packages-js/fields-advanced/src/`](src/)
- Tests: [`packages-js/fields-advanced/tests/`](tests/)
