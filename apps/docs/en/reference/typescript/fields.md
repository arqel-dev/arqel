# `@arqel-dev/fields` — API Reference

21 rich React inputs registered via the `FieldRegistry` from `@arqel-dev/ui`. 12 subpath entry points.

```ts
import '@arqel-dev/fields/register';                           // side-effect: registers all 21
import { TextInput, SlugInput, slugify } from '@arqel-dev/fields';
// or subset
import { TextInput, EmailInput } from '@arqel-dev/fields/text';
import { CurrencyInput } from '@arqel-dev/fields/number';
```

## Catalog

Each component receives `FieldRendererProps` (re-exported from `@arqel-dev/ui/form`):

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
| `/select` | `SelectInput`, `MultiSelectInput` (removable chips), `RadioGroup` (role=radiogroup) |
| `/relationship` | `BelongsToInput` (async fetch + 300ms debounce + role=combobox/listbox), `HasManyReadonly` |
| `/date` | `DateInput`, `DateTimeInput` |
| `/file` | `FileInput` (drag-drop in a `<section>`), `ImageInput` (URL.createObjectURL preview) |
| `/slug` | `SlugInput` + `slugify` helper |
| `/color` | `ColorInput` (native picker + presets + hex text) |
| `/hidden` | `HiddenInput` |

## `slugify(input: string): string`

Normalizes to `[a-z0-9-]+`:

```ts
slugify('Hello World!')        // 'hello-world'
slugify(' --foo--bar-- ')      // 'foo-bar'
slugify('São Paulo')           // 'sao-paulo' (NFD diacritics stripping)
```

## `register.ts` side-effect

The only file with `sideEffects: true` in `package.json`. Import once at boot:

```tsx
// resources/js/app.tsx
import '@arqel-dev/ui/styles.css';
import '@arqel-dev/fields/register';
import { createArqelApp } from '@arqel-dev/react/inertia';
```

From this import on, `<FieldRenderer>` resolves `field.component === 'TextInput'` to the rich component. Without the import, it falls back to the native `@arqel-dev/ui/form` fallback.

## Custom override

```tsx
import { registerField } from '@arqel-dev/ui/form';
import { MyFancyText } from './MyFancyText';

registerField('TextInput', MyFancyText);                   // after register.ts
```

## Custom Field type

Three pieces (server PHP + React + register):

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
import type { FieldRendererProps } from '@arqel-dev/ui/form';

export function RatingInput({ field, value, onChange, errors, inputId }: FieldRendererProps) {
  // ...
}
```

```tsx
// resources/js/app.tsx
import '@arqel-dev/fields/register';
import { registerField } from '@arqel-dev/ui/form';
import { RatingInput } from './fields/RatingInput';

registerField('RatingInput', RatingInput);
```

## Related

- SKILL: [`packages-js/fields-js/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages-js/fields-js/SKILL.md)
- Concepts: [`/guide/fields`](/guide/fields), [`/advanced/custom-fields`](/advanced/custom-fields)
- Back: [`@arqel-dev/types`](/reference/typescript/types)
