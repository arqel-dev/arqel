# SKILL.md — arqel-dev/workflow (JS)

## Purpose

Pacote React que entrega os componentes UI do Arqel Workflow. Por
enquanto exporta apenas `<StateTransition>`, espelhando o PHP
`Arqel\Workflow\Fields\StateTransitionField` (component string
`arqel-dev/workflow/StateTransition`).

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
- Component name canônico: `arqel-dev/workflow/StateTransition`. Não
  renomear — o PHP usa essa string.
- Lint: Biome (`pnpm lint`); testes: Vitest + Testing Library; build:
  tsup.
- Side-effects ficam isolados em `dist/register.js` para tree-shaking.

## Examples

```tsx
import { StateTransition } from '@arqel-dev/workflow';

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

Para registrar via FieldRegistry do `@arqel-dev/ui`:

```ts
import '@arqel-dev/workflow/register';
```

## Anti-patterns

- Fazer `fetch`/Inertia direto dentro do componente — quebra a regra
  apresentacional. Use `onTransition` ou consuma o `CustomEvent`.
- Misturar lógica de autorização no componente — autoridade já vem
  resolvida pelo PHP (`authorized: boolean`).
- Renomear o component name para algo sem prefixo `arqel-dev/` — o PHP
  field aponta exatamente para `arqel-dev/workflow/StateTransition`.

## WorkflowVisualizer (WF-005)

Componente apresentacional que converte uma definição de state machine
num diagrama Mermaid (`graph LR` ou `graph TB`). **Não** carrega o
runtime do mermaid — devolve a fonte como string num `<pre
className="language-mermaid">` por default, ou delega via prop
`renderer` quando o consumidor já tem mermaid configurado.

```tsx
import { WorkflowVisualizer } from '@arqel-dev/workflow';

<WorkflowVisualizer
  definition={{
    field: 'order_state',
    states: {
      'App\\States\\Pending': { label: 'Pendente' },
      'App\\States\\Paid':    { label: 'Pago', color: '#10b981' },
      'App\\States\\Shipped': { label: 'Enviado' },
    },
    transitions: [
      { from: 'App\\States\\Pending', to: 'App\\States\\Paid', label: 'Pay' },
      { from: 'App\\States\\Paid',    to: 'App\\States\\Shipped', label: 'Ship' },
    ],
  }}
  currentState="App\\States\\Paid"
  direction="LR"
/>
```

Renderer customizado (consumidor já tem mermaid runtime):

```tsx
import mermaid from 'mermaid';
import { WorkflowVisualizer } from '@arqel-dev/workflow';

<WorkflowVisualizer
  definition={definition}
  renderer={(src) => {
    // o consumidor decide como virar SVG — esta lib é dep-free
    return <MyMermaidSvg source={src} />;
  }}
/>
```

A função pura `buildMermaidSource(definition, currentState?, direction?)`
também é exportada para uso fora de React (ex.: gerar `.mmd` em build
time). FQCN como `App\\States\\Pending` viram nodes `Pending`,
`Paid`, etc; `from: null` numa transição é expandido para edges
saindo de **todos** os states; `from: ['A', 'B']` produz uma edge por
origem; o `currentState` recebe um `style ... fill:#fbbf24` no fim,
sobrepondo qualquer cor declarada no state.

## Related

- PHP field: `packages/workflow/src/Fields/StateTransitionField.php`
- Ticket: `PLANNING/10-fase-3-avancadas.md` → WF-003 (slice React) e WF-005 (visualizer)
- FieldRegistry: `packages-js/ui/src/form/FieldRegistry.tsx`
