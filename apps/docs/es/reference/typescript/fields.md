# `@arqel-dev/fields` — Referencia de API

21 inputs ricos de React registrados vía el `FieldRegistry` de `@arqel-dev/ui`. 12 entry points por subpath.

```ts
import '@arqel-dev/fields/register';                           // side-effect: registers all 21
import { TextInput, SlugInput, slugify } from '@arqel-dev/fields';
// or subset
import { TextInput, EmailInput } from '@arqel-dev/fields/text';
import { CurrencyInput } from '@arqel-dev/fields/number';
```

## Catálogo

Cada componente recibe `FieldRendererProps` (re-exportado desde `@arqel-dev/ui/form`):

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

| Subpath | Componentes |
|---|---|
| `/text` | `TextInput`, `TextareaInput`, `EmailInput`, `UrlInput`, `PasswordInput` (toggle reveal `aria-pressed`) |
| `/number` | `NumberInput` (botones stepper), `CurrencyInput` (Intl-format en blur, raw en focus) |
| `/boolean` | `Checkbox`, `Toggle` (role=switch + thumb iOS) |
| `/select` | `SelectInput`, `MultiSelectInput` (chips removibles), `RadioGroup` (role=radiogroup) |
| `/relationship` | `BelongsToInput` (fetch async + debounce 300ms + role=combobox/listbox), `HasManyReadonly` |
| `/date` | `DateInput`, `DateTimeInput` |
| `/file` | `FileInput` (drag-drop en un `<section>`), `ImageInput` (preview con URL.createObjectURL) |
| `/slug` | `SlugInput` + helper `slugify` |
| `/color` | `ColorInput` (picker nativo + presets + texto hex) |
| `/hidden` | `HiddenInput` |

## `slugify(input: string): string`

Normaliza a `[a-z0-9-]+`:

```ts
slugify('Hello World!')        // 'hello-world'
slugify(' --foo--bar-- ')      // 'foo-bar'
slugify('São Paulo')           // 'sao-paulo' (NFD diacritics stripping)
```

## Side-effect de `register.ts`

El único archivo con `sideEffects: true` en `package.json`. Importa una vez en boot:

```tsx
// resources/js/app.tsx
import '@arqel-dev/ui/styles.css';
import '@arqel-dev/fields/register';
import { createArqelApp } from '@arqel-dev/react/inertia';
```

A partir de este import, `<FieldRenderer>` resuelve `field.component === 'TextInput'` al componente rico. Sin el import, hace fallback al nativo de `@arqel-dev/ui/form`.

## Override personalizado

```tsx
import { registerField } from '@arqel-dev/ui/form';
import { MyFancyText } from './MyFancyText';

registerField('TextInput', MyFancyText);                   // after register.ts
```

## Tipo de Field personalizado

Tres piezas (PHP del servidor + React + register):

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

## Relacionado

- SKILL: [`packages-js/fields-js/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages-js/fields-js/SKILL.md)
- Conceptos: [`/es/guide/fields`](/es/guide/fields), [`/es/advanced/custom-fields`](/es/advanced/custom-fields)
- Volver: [`@arqel-dev/types`](/es/reference/typescript/types)
