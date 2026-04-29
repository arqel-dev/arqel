# `@arqel/fields` — API Reference

21 rich React inputs registrados via `FieldRegistry` de `@arqel/ui`. 12 entry points subpath.

```ts
import '@arqel/fields/register';                           // side-effect: registra os 21
import { TextInput, SlugInput, slugify } from '@arqel/fields';
// ou subset
import { TextInput, EmailInput } from '@arqel/fields/text';
import { CurrencyInput } from '@arqel/fields/number';
```

## Catálogo

Cada componente recebe `FieldRendererProps` (re-exportado de `@arqel/ui/form`):

```ts
type FieldRendererProps = {
  field: FieldSchema;
  value: unknown;
  onChange: (value: unknown) => void;
  errors?: string[];
  disabled?: boolean;
  inputId: string;
  describedBy?: string;
};
```

| Subpath | Components |
|---|---|
| `/text` | `TextInput`, `TextareaInput`, `EmailInput`, `UrlInput`, `PasswordInput` (toggle reveal `aria-pressed`) |
| `/number` | `NumberInput` (stepper buttons), `CurrencyInput` (Intl-format on blur, raw on focus) |
| `/boolean` | `Checkbox`, `Toggle` (role=switch + iOS thumb) |
| `/select` | `SelectInput`, `MultiSelectInput` (chips removíveis), `RadioGroup` (role=radiogroup) |
| `/relationship` | `BelongsToInput` (async fetch + 300ms debounce + role=combobox/listbox), `HasManyReadonly` |
| `/date` | `DateInput`, `DateTimeInput` |
| `/file` | `FileInput` (drag-drop em `<section>`), `ImageInput` (URL.createObjectURL preview) |
| `/slug` | `SlugInput` + helper `slugify` |
| `/color` | `ColorInput` (native picker + presets + hex text) |
| `/hidden` | `HiddenInput` |

## `slugify(input: string): string`

Normaliza para `[a-z0-9-]+`:

```ts
slugify('Hello World!')        // 'hello-world'
slugify(' --foo--bar-- ')      // 'foo-bar'
slugify('São Paulo')           // 'sao-paulo' (NFD diacritics stripping)
```

## `register.ts` side-effect

Único arquivo com `sideEffects: true` no `package.json`. Importar uma vez no boot:

```tsx
// resources/js/app.tsx
import '@arqel/ui/styles.css';
import '@arqel/fields/register';
import { createArqelApp } from '@arqel/react/inertia';
```

A partir desse import, `<FieldRenderer>` resolve `field.component === 'TextInput'` para o componente rico. Sem o import, cai no fallback nativo de `@arqel/ui/form`.

## Override custom

```tsx
import { registerField } from '@arqel/ui/form';
import { MyFancyText } from './MyFancyText';

registerField('TextInput', MyFancyText);                   // depois do register.ts
```

## Custom Field type

Tres pedaços (server PHP + React + register):

```php
// app/Arqel/Fields/RatingField.php
final class RatingField extends Field
{
    protected string $type = 'rating';
    protected string $component = 'RatingInput';
}
```

```tsx
// resources/js/fields/RatingInput.tsx
import type { FieldRendererProps } from '@arqel/ui/form';

export function RatingInput({ field, value, onChange, errors, inputId }: FieldRendererProps) {
  // ...
}
```

```tsx
// resources/js/app.tsx
import '@arqel/fields/register';
import { registerField } from '@arqel/ui/form';
import { RatingInput } from './fields/RatingInput';

registerField('RatingInput', RatingInput);
```

## Related

- SKILL: [`packages-js/fields-js/SKILL.md`](https://github.com/arqel/arqel/blob/main/packages-js/fields-js/SKILL.md)
- Conceitos: [`/guide/fields`](/guide/fields), [`/advanced/custom-fields`](/advanced/custom-fields)
- Volta: [`@arqel/types`](/reference/typescript/types)
