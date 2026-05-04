# @arqel-dev/workflow

Componentes React do Arqel Workflow.

Exporta `<StateTransition>`, par React do PHP
`Arqel\Workflow\Fields\StateTransitionField`. Veja [SKILL.md](./SKILL.md)
para o contrato completo de props e exemplos.

## Instalação

```bash
pnpm add @arqel-dev/workflow
```

## Uso

```ts
import '@arqel-dev/workflow/register'; // registra no FieldRegistry de @arqel-dev/ui

// ou import direto:
import { StateTransition } from '@arqel-dev/workflow';
```

Licença: MIT.
