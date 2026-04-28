# SKILL.md — arqel/form

> Contexto canónico para AI agents. Estrutura conforme `PLANNING/04-repo-structure.md` §11.

## Purpose

`arqel/form` constrói formulários declarativos sobre `arqel/fields`. Layout components (Section, Fieldset, Grid, Columns, Group, Tabs) compõem hierarquias de campos; o builder gera o JSON consumido pelo lado React e (via FormRequestGenerator) classes FormRequest com regras de validação espelhadas.

## Status (FORM-001)

Apenas o esqueleto:

- `composer.json` com deps em `arqel/core: @dev` + `arqel/fields: @dev`
- `FormServiceProvider` registado via auto-discovery
- PSR-4 `Arqel\Form\` → `src/`

Ainda **NÃO existem**:

- `Arqel\Form\Form` builder (FORM-002)
- Layout components (FORM-003..005)
- `FormRequestGenerator` (FORM-007)
- Inertia helpers (FORM-008)

## Conventions

- `declare(strict_types=1)` obrigatório
- Form é apenas estrutura — Fields traem o tipo, validação e auth per-field
- Pacote depende de FIELDS (e CORE) — sem dependência inversa

## Anti-patterns

- ❌ Field logic dentro de Form — pertence a `arqel/fields`
- ❌ Manipulação de query no Form — query é responsabilidade do controller (CORE-006)

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §FORM-001..010
- ADRs: [ADR-001](../../PLANNING/03-adrs.md) Inertia-only · [ADR-008](../../PLANNING/03-adrs.md) Pest 3
