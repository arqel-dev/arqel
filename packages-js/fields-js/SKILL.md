# SKILL.md — @arqel/fields (JS)

> Contexto canónico para AI agents.

## Purpose

`@arqel/fields` é o pacote de inputs ricos do lado React. Cada componente registra-se no `FieldRegistry` de `@arqel/ui` (via `import '@arqel/fields/register'`) e substitui os fallbacks nativos do `<FieldRenderer>`. O nome usado em `registerField('TextInput', ...)` corresponde ao retornado por `Field::component()` no PHP — a Resource servidor não precisa saber nada do React.

## Status

**Entregue (FIELDS-JS-001 + FIELDS-JS-002):**

- Pacote `@arqel/fields` com 5 entry points subpath (`./`, `./register`, `./text`, `./number`, `./boolean`)
- 9 components básicos:
  - **text/**: `TextInput`, `TextareaInput`, `EmailInput`, `UrlInput`, `PasswordInput` (com toggle reveal)
  - **number/**: `NumberInput` (com stepper buttons), `CurrencyInput` (Intl-format on blur)
  - **boolean/**: `Checkbox`, `Toggle` (role="switch")
- Side-effect `register.ts` registra todos os 9 no FieldRegistry
- Estilo via CSS vars de `@arqel/ui` (sem hardcode); `aria-invalid` quando há erros

**Por chegar:**

- FIELDS-JS-003: `SelectInput`, `MultiSelectInput`, `RadioGroup`, `BelongsToInput` (async combobox), `HasManyReadonly`, `DateInput`, `DateTimeInput`, `FileInput`, `ImageInput`
- FIELDS-JS-004: `SlugInput`, `ColorInput`, `HiddenInput`
- FIELDS-JS-005: `getRegisteredFields()` helper + smoke check
- FIELDS-JS-006: testes adicionais + coverage ≥ 80%

## Key Contracts

```tsx
// resources/js/app.tsx
import '@arqel/ui/styles.css';
import '@arqel/fields/register'; // side effect: registra inputs ricos
import { createArqelApp } from '@arqel/react/inertia';

createArqelApp({
  appName: 'Acme Admin',
  pages: import.meta.glob('./Pages/**/*.tsx'),
});
```

A partir desse import, `<FieldRenderer>` resolve `field.component === 'TextInput'` para o componente rico. Sem o import, cai no fallback nativo de `@arqel/ui/form` (`nativeFields.tsx`).

```tsx
// Override custom: registre depois do side-effect import
import { registerField } from '@arqel/ui/form';
import { MyFancyText } from './MyFancyText';

registerField('TextInput', MyFancyText);
```

## Conventions

- **Nome do componente** segue o `Field::component()` retornado pelo PHP
- **Props canônicas** vêm de `FieldRendererProps` (re-exportado de `@arqel/ui/form`): `field`, `value`, `onChange`, `errors`, `disabled`, `inputId`, `describedBy`
- **Estilos** sempre via `@arqel/ui/utils#cn` + CSS vars (`--color-arqel-*`) — nunca hardcode
- **A11y**: `aria-invalid` quando `errors.length > 0`, `aria-describedby` propagado, labels associados via `inputId` (gerenciado pelo `<FieldRenderer>`)
- **Side-effect entry**: `register.ts` é o único arquivo com `sideEffects: true` no `package.json`

## Anti-patterns

- ❌ **Importar `register.ts` mais de uma vez** — registra 2x o mesmo componente; se você precisa override, chame `registerField` direto
- ❌ **Criar wrapper `<label>` interno** — `<FieldRenderer>` já faz isso; o componente recebe `inputId` para `<input id={inputId}>`
- ❌ **Hardcode de cor** — usa CSS vars
- ❌ **Importar de `@arqel/fields/text/TextInput.js`** — usa subpaths declarados (`@arqel/fields/text`)
- ❌ **Render label dentro do componente** — `<FieldRenderer>` é o dono do label

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §FIELDS-JS-001..006
- API: [`PLANNING/06-api-react.md`](../../PLANNING/06-api-react.md) §10
- Source: [`packages-js/fields-js/src/`](src/)
- Tests: [`packages-js/fields-js/tests/`](tests/)
