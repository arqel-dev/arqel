# SKILL.md — arqel/workflow (JS)

## Purpose

Pacote React que entrega os componentes UI do Arqel Workflow. Por
enquanto exporta apenas `<StateTransition>`, espelhando o PHP
`Arqel\Workflow\Fields\StateTransitionField` (component string
`arqel/workflow/StateTransition`).

O componente é **puramente apresentacional** — não faz fetch, não
conhece Inertia. Disparos de transição saem como `CustomEvent`
(`arqel:state-transition`) ou via callback `onTransition`, e a
camada de aplicação decide se vira `router.post(...)` ou outra coisa.

## Key Contracts

```ts
interface StateTransitionProps {
  name: string;
  value: unknown;
  props: {
    currentState: { name; label; color?; icon? } | null;
    transitions: { from; to; label; authorized }[];
    history: { from; to; at; by? }[];
    showDescription: boolean;
    showHistory: boolean;
    transitionsAttribute: string;
  };
  record?: { id?: number | string };
  csrfToken?: string;
  onTransition?: (from, to) => void;
}
```

Evento global ao clicar numa transição autorizada (sem `onTransition`):

```ts
document.addEventListener('arqel:state-transition', (e) => {
  const { from, to, name, recordId } = e.detail;
});
```

## Conventions

- TypeScript strict, sem `any` (apenas asserts pontuais devidamente
  comentados em `register.ts`).
- Classes PHP equivalentes ficam em `packages/workflow/src/Fields/`.
- Component name canônico: `arqel/workflow/StateTransition`. Não
  renomear — o PHP usa essa string.
- Lint: Biome (`pnpm lint`); testes: Vitest + Testing Library; build:
  tsup.
- Side-effects ficam isolados em `dist/register.js` para tree-shaking.

## Examples

```tsx
import { StateTransition } from '@arqel/workflow';

<StateTransition
  name="state"
  value="draft"
  props={{
    currentState: { name: 'draft', label: 'Draft', color: '#cccccc' },
    transitions: [{ from: 'draft', to: 'published', label: 'Publish', authorized: true }],
    history: [],
    showDescription: false,
    showHistory: false,
    transitionsAttribute: 'state',
  }}
  record={{ id: 42 }}
/>
```

Para registrar via FieldRegistry do `@arqel/ui`:

```ts
import '@arqel/workflow/register';
```

## Anti-patterns

- Fazer `fetch`/Inertia direto dentro do componente — quebra a regra
  apresentacional. Use `onTransition` ou consuma o `CustomEvent`.
- Misturar lógica de autorização no componente — autoridade já vem
  resolvida pelo PHP (`authorized: boolean`).
- Renomear o component name para algo sem prefixo `arqel/` — o PHP
  field aponta exatamente para `arqel/workflow/StateTransition`.

## Related

- PHP field: `packages/workflow/src/Fields/StateTransitionField.php`
- Ticket: `PLANNING/10-fase-3-avancadas.md` → WF-003 (slice React)
- FieldRegistry: `packages-js/ui/src/form/FieldRegistry.tsx`
