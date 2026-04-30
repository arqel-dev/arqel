# SKILL.md — @arqel/fields-advanced

> Contexto canónico para AI agents.

## Purpose

`@arqel/fields-advanced` é o pacote de inputs avançados do lado React: componentes mais ricos que os básicos de `@arqel/fields`. Cada componente registra-se manualmente no `FieldRegistry` de `@arqel/ui` (sem auto-register — o app hospedeiro decide quais carregar).

## Key Contracts

- Cada componente é um `ComponentType<FieldRendererProps>` — recebe `field`, `value`, `onChange`, `errors`, `disabled`, `inputId`, `describedBy`.
- Props específicas do field são lidas via `(field as { props: unknown }).props` com cast defensivo (defaults preenchem campos faltantes).
- Renderiza com classes Tailwind + CSS vars de `@arqel/ui` (`--color-arqel-*`).
- Sem dependências externas além de React.

## Conventions

- Estado interno espelha `value` via `useEffect(..., [value])`.
- A11y: roles `combobox`/`listbox`/`option` quando há sugestões; `aria-invalid` em erro; `aria-label` derivado de `field.label`.
- Cada chip removível tem `aria-label="Remove tag <name>"` (ou equivalente).

## Examples

```tsx
import { TagsInput } from '@arqel/fields-advanced/tags';
import { registerField } from '@arqel/ui/form';

registerField('TagsInput', TagsInput);
```

## Anti-patterns

- Importar Headless UI / Combobox libs externos aqui — usar React puro.
- Confiar em `field.props.*` sem cast/defaults — o servidor pode não enviar tudo.
- Modificar `value` sem chamar `onChange`.

## Related

- `packages/fields-advanced/src/Types/TagsField.php` (PHP-side definition)
- `packages-js/ui/src/form/FieldRegistry.tsx`
- Spec: `PLANNING/09-fase-2-essenciais.md` §FIELDS-ADV-016
