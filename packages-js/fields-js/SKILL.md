# SKILL.md — @arqel-dev/fields (JS)

> Contexto canónico para AI agents.

## Purpose

`@arqel-dev/fields` é o pacote de inputs ricos do lado React. Cada componente registra-se no `FieldRegistry` de `@arqel-dev/ui` (via `import '@arqel-dev/fields/register'`) e substitui os fallbacks nativos do `<FieldRenderer>`. O nome usado em `registerField('TextInput', ...)` corresponde ao retornado por `Field::component()` no PHP — a Resource servidor não precisa saber nada do React.

## Status

**Entregue (FIELDS-JS-001..006):**

- Pacote `@arqel-dev/fields` com 12 entry points subpath (`./`, `./register`, `./text`, `./number`, `./boolean`, `./select`, `./relationship`, `./date`, `./file`, `./slug`, `./color`, `./hidden`)
- **21 components no total** — um por field type canônico:
  - **text/**: `TextInput`, `TextareaInput`, `EmailInput`, `UrlInput`, `PasswordInput` (toggle reveal com `aria-pressed`)
  - **number/**: `NumberInput` (stepper buttons), `CurrencyInput` (Intl-format on blur, raw on focus)
  - **boolean/**: `Checkbox`, `Toggle` (role=switch + iOS track/thumb)
  - **select/**: `SelectInput` (native), `MultiSelectInput` (chips removíveis + native multiple), `RadioGroup` (role=radiogroup + inline/stacked)
  - **relationship/**: `BelongsToInput` (async combobox via fetch + 300ms debounce ao `field.props.searchRoute`, role=combobox/listbox), `HasManyReadonly` (lista flat readonly)
  - **date/**: `DateInput` (`type="date"` nativo + min/max), `DateTimeInput` (`type="datetime-local"` + step se `seconds`)
  - **file/**: `FileInput` (drag-drop + section semântica, armazena `File` no form state), `ImageInput` (preview via `URL.createObjectURL`, sem crop em Phase 1)
  - **slug/**: `SlugInput` (normaliza para `[a-z0-9-]+` no keystroke + helper `slugify`)
  - **color/**: `ColorInput` (native picker + presets + hex text input)
  - **hidden/**: `HiddenInput` (`type="hidden"` value carrier)
- Side-effect `register.ts` registra todos os 21 no FieldRegistry
- `getRegisteredFields()` (re-exportado de `@arqel-dev/ui/form`) retorna nomes registrados ordenados
- 23 testes Vitest passando — registry roundtrip, behaviour + a11y de cada componente
- Estilo via CSS vars de `@arqel-dev/ui` (sem hardcode); `aria-invalid` quando há erros

**Por chegar (Phase 2):**

- Image crop (`react-image-crop`)
- Date-picker custom (`react-day-picker`)
- Combobox searchable de Base UI para `SelectInput`
- `SlugInput` auto-derivado de outro field (depende de FormContext exposed pelo `useArqelForm`)

## Creating a custom field

```tsx
// resources/js/fields/MoneyInput.tsx
import type { FieldRendererProps } from '@arqel-dev/ui/form';

export function MoneyInput({ field, value, onChange, errors, inputId }: FieldRendererProps) {
  const hasError = errors !== undefined && errors.length > 0;
  return (
    <input
      id={inputId}
      type="text"
      inputMode="decimal"
      className="…"
      value={typeof value === 'string' ? value : ''}
      aria-invalid={hasError || undefined}
      onChange={(e) => onChange(e.target.value)}
    />
  );
}
```

```tsx
// resources/js/app.tsx
import '@arqel-dev/ui/styles.css';
import '@arqel-dev/fields/register';
import { registerField } from '@arqel-dev/ui/form';
import { MoneyInput } from './fields/MoneyInput';

registerField('MoneyInput', MoneyInput); // depois do register.ts default
```

```php
// app/Arqel/Fields/MoneyField.php
final class MoneyField extends Field
{
    protected string $type = 'money';
    protected string $component = 'MoneyInput';
}
```

## Key Contracts

```tsx
// resources/js/app.tsx
import '@arqel-dev/ui/styles.css';
import '@arqel-dev/fields/register'; // side effect: registra inputs ricos
import { createArqelApp } from '@arqel-dev/react/inertia';

createArqelApp({
  appName: 'Acme Admin',
  pages: import.meta.glob('./Pages/**/*.tsx'),
});
```

A partir desse import, `<FieldRenderer>` resolve `field.component === 'TextInput'` para o componente rico. Sem o import, cai no fallback nativo de `@arqel-dev/ui/form` (`nativeFields.tsx`).

```tsx
// Override custom: registre depois do side-effect import
import { registerField } from '@arqel-dev/ui/form';
import { MyFancyText } from './MyFancyText';

registerField('TextInput', MyFancyText);
```

## Conventions

- **Nome do componente** segue o `Field::component()` retornado pelo PHP
- **Props canônicas** vêm de `FieldRendererProps` (re-exportado de `@arqel-dev/ui/form`): `field`, `value`, `onChange`, `errors`, `disabled`, `inputId`, `describedBy`
- **Estilos** sempre via `@arqel-dev/ui/utils#cn` + CSS vars (`--color-arqel-*`) — nunca hardcode
- **A11y**: `aria-invalid` quando `errors.length > 0`, `aria-describedby` propagado, labels associados via `inputId` (gerenciado pelo `<FieldRenderer>`)
- **Side-effect entry**: `register.ts` é o único arquivo com `sideEffects: true` no `package.json`

## Anti-patterns

- ❌ **Importar `register.ts` mais de uma vez** — registra 2x o mesmo componente; se você precisa override, chame `registerField` direto
- ❌ **Criar wrapper `<label>` interno** — `<FieldRenderer>` já faz isso; o componente recebe `inputId` para `<input id={inputId}>`
- ❌ **Hardcode de cor** — usa CSS vars
- ❌ **Importar de `@arqel-dev/fields/text/TextInput.js`** — usa subpaths declarados (`@arqel-dev/fields/text`)
- ❌ **Render label dentro do componente** — `<FieldRenderer>` é o dono do label

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §FIELDS-JS-001..006
- API: [`PLANNING/06-api-react.md`](../../PLANNING/06-api-react.md) §10
- Source: [`packages-js/fields-js/src/`](src/)
- Tests: [`packages-js/fields-js/tests/`](tests/)
