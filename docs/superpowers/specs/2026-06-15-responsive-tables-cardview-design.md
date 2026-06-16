# Design — Fase 2 do loop responsivo: DataTable card-view mobile

**Data:** 2026-06-15
**Branch alvo:** `round-design/responsive-tables` (a partir de `main`)
**Pacote do fix:** `@arqel-dev/ui` (`packages-js/ui/src/table/DataTable.tsx`)
**Stage de avaliação:** `apps/showcase`

## Contexto

A Fase 0-1 (PR #256, merged como `b8e8e6c`) fechou a classe de defeitos
"overflow horizontal grosseiro + touch-targets" nas 6 superfícies. O baseline
scan (`responsive-baseline.spec.ts`, `@baseline`) passa 9/9 contra o `main`.

Porém o gate atual é **cego** ao maior defeito UX restante: em 360/640px a
`<table>` do índice (ex.: `/admin/posts`, ~626px de largura) **scrolla
horizontalmente** dentro do seu wrapper `overflow-x-auto`. Isso *passa* o teste
de overflow (o body não alarga) mas obriga scroll lateral para ler cada linha —
péssima experiência mobile. O padrão correto (Filament/Linear) é **card-view**:
em mobile, cada linha vira um card empilhado label:valor.

### Estado do código (verificado)

- `DataTable` (`packages-js/ui/src/table/DataTable.tsx`) é construída sobre
  TanStack Table v8. Renderiza uma única `<table>` num wrapper
  `w-full min-w-0 overflow-x-auto`.
- `ColumnSchema` (`packages-js/types/src/tables.ts`) **já tem** `hiddenOnMobile`,
  `label`, `align`, `width`, `sortable`.
- **Bug latente:** o `<td>` honra `col.hiddenOnMobile` (`hidden md:table-cell`,
  linha 252) mas o `<th>` correspondente **não** — o cabeçalho da coluna
  oculta-no-mobile continua presente, desalinhando a tabela em telas estreitas.
- `TableCell` (`./cells.js`) formata o valor por tipo (badge/date/boolean/…).
  Será reusado no card-view para não duplicar formatação.

## Decisão de design

**Padrão escolhido:** Card stack (decisão do usuário).

- **≥768px (`md`):** a `<table>` atual, intacta, envolvida em `hidden md:block`.
- **<768px:** um novo bloco de cards `md:hidden`, a partir dos **mesmos**
  `records` + `visibleColumns` + `rowActions`. Cada record → um `<article>`
  (card) com:
  - **Header do card:** checkbox de seleção (se `enableSelection`) + cluster de
    `rowActions` (se houver).
  - **Corpo:** uma lista de pares **label : valor**, um por coluna visível e
    **não** `hiddenOnMobile`, reusando `<TableCell>` para o valor.

Sem JS de medição de viewport — só utilitários Tailwind responsivos
(`hidden`/`md:block`/`md:hidden`), para evitar hydration mismatch e flicker.
Ambos os modos compartilham a lógica de seleção/sort existente.

`hiddenOnMobile`: colunas marcadas **não** aparecem como pares no card
(consistente com a intenção atual). Corrige-se também o bug do `<th>` aplicando
`hidden md:table-cell` ao cabeçalho, fechando o desalinhamento.

## Métrica & gate (TDD)

Novo spec: `apps/showcase/tests/e2e/responsive-tables.spec.ts` (escopo `demo`),
em `/admin/posts`:

- **<768px (360, 640):** nenhuma `<table>` visível; ≥1 `<article>` card visível;
  cada card contém os pares label:valor das colunas não-`hiddenOnMobile`.
- **≥768px (768, 1024, 1440):** `<table>` visível, cards ocultos (regressão
  desktop).
- **Mantém:** zero overflow horizontal nos 5 viewports; touch-targets dos
  controles do card (checkbox/ações) ≥44px em mobile.

Helper extra em `responsive.ts` se necessário (ex.: assert de visível/oculto).

## Critérios de aceite

- [ ] `responsive-tables.spec.ts` verde nos 5 viewports.
- [ ] Bug do `<th>` `hiddenOnMobile` corrigido.
- [ ] Card-view reusa `TableCell` (sem duplicar formatação).
- [ ] Baseline `@baseline` ainda 9/9.
- [ ] Suíte default verde (sem regressão desktop).
- [ ] biome + tsc limpos no pacote `ui`.
- [ ] CI limpo; PR squash-merged.

## Restrições (do projeto)

- Apenas shadcn primitives + cva + tokens OKLCH + utilitários Tailwind. Sem CSS
  ad-hoc nem componentes hand-rolled.
- Cada fix de componente compartilhado assere mobile-pass **e** no-desktop-regress.
- DCO `--signoff`; Conventional Commits (escopo `ui` p/ framework, `demo` p/
  showcase/test); subjects ≤100 chars; `--no-verify` só se o husky quebrado
  bloquear (após biome+test no container). v0.x tag é passo manual do mantenedor.
