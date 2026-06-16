# Design — Fase 3 do loop responsivo: menu de ações bottom-sheet mobile

**Data:** 2026-06-16
**Branch alvo:** `round-design/responsive-modals` (a partir de `main`)
**Pacote do fix:** `@arqel-dev/ui` (`packages-js/ui/src/action/ActionMenu.tsx`)
**Stage de avaliação:** `apps/showcase`

## Contexto

As Fases 0-2 (PRs #256, #257, merged `b8e8e6c` / `e930a5f`) fecharam a classe
grosseira "overflow horizontal + touch-targets" e entregaram o card-view mobile
da `DataTable`. O baseline scan (`responsive-baseline.spec.ts`, `@baseline`)
passa 9/9 contra o `main`, confirmando que essa classe está **esgotada**.

A sonda fina da Fase 3 (typography / modals / dashboard) revelou o sinal mobile
mais forte nos **modais/menus**: o `DropdownMenu` (Radix popper) das ações de
linha fica **fixo em 192px** (`min-w-[12rem]`) em **todos** os viewports. Em
360px ele ocupa só **53%** da largura (`widthRatio ≈ 0.53`), mantendo geometria
desktop num ecrã estreito — em vez do idioma mobile canônico (Filament / Linear
/ iOS action sheet): um **bottom-sheet de largura cheia** com itens ≥44px.

Typography (H1 fixo, sem overflow/truncamento) é só estético — sem defeito de
responsividade objetivamente mensurável. Dashboard reflui 1→2→3 tracks
corretamente; o único overflow (chart-SVG) some nos viewports mobile. Ambos
ficam fora do escopo objetivo do loop.

### Estado do código (verificado)

- `ActionMenu` (`packages-js/ui/src/action/ActionMenu.tsx`): quando
  `actions.length > inlineThreshold` (default 3), colapsa num `DropdownMenu`
  com `DropdownMenuContent align="end" className="min-w-[12rem]"`. Os modais de
  gate (`ConfirmDialog`, `ActionFormModal`) são irmãos, sobrevivem ao menu
  fechar via state `confirmAction` / `formAction`.
- `Sheet` (`packages-js/ui/src/shadcn/ui/sheet.tsx`): **já existe**, shadcn,
  baseado em **Radix Dialog** (já no projeto), com `side="bottom"`
  (`inset-x-0 bottom-0 h-auto border-t` + slide animations). **Sem dependência
  nova** (`vaul` não é necessário).
- `DropdownMenuItem` hoje usa `py-1.5` (≈32px) — abaixo de 44px.

## Decisão de design

**Idioma mobile:** bottom-sheet (Sheet shadcn `side="bottom"`) — decisão do
usuário. **Alternância:** dual-render Tailwind (`md:hidden` / `hidden md:…`),
zero JS de viewport — mesmo padrão do card-view da Fase 2, sem hydration
mismatch nem flicker.

- **≥768px:** o `DropdownMenu` atual, intacto, no subtree `hidden md:contents`.
- **<768px:** um `Sheet side="bottom"` no subtree `md:hidden`, a partir dos
  **mesmos** `actions`. Cada ação vira um item de **largura cheia**, `min-h-11`
  (≥44px), empilhado verticalmente. Um `SheetTitle` "Actions" (visível ou
  sr-only) satisfaz o requisito de título acessível do Radix Dialog.

O trigger (botão `⋯` `size="icon-touch"`, `aria-label="Actions"`) e os modais
de gate (`ConfirmDialog`, `ActionFormModal`) são **compartilhados** entre os dois
modos — só a *superfície de lista* (Dropdown vs. Sheet) é dual-renderizada. A
state machine (`confirmAction` / `formAction`) e os handlers (`handleSelect`,
`handleConfirm`) **não mudam**.

Para não duplicar `actions.map(...)`, extrai-se um helper interno que produz a
lista, parametrizado pelo "renderItem" (DropdownMenuItem vs. botão-do-sheet).

## Data flow

`ArqelIndexPage` → `rowActions` → `ActionMenu({ actions, onInvoke })`. Inline
(`≤ inlineThreshold`) inalterado. No modo collapsed, **ambos** Dropdown e Sheet
são montados; o CSS mostra um. Selecionar um item → `handleSelect` → abre
`ConfirmDialog` / `ActionFormModal` (irmãos compartilhados) ou chama `onInvoke`.
O Sheet fecha via `onOpenChange(false)` ao selecionar, espelhando o `onSelect`
do Dropdown.

**Acessibilidade:** o Sheet (Radix Dialog) traz focus-trap, `aria-modal`,
Escape e click-outside de graça; o `SheetTitle` evita o warning de título.

## Edge cases

- `inlineThreshold` (`≤3` ações): continua inline (botões), sem Dropdown nem
  Sheet — inalterado.
- Ação `disabled`: o item do Sheet respeita (sem invoke, opacidade reduzida).
- Ação `destructive`: cor destructive no item do Sheet, espelhando o Dropdown.
- Card-view (Fase 2): o trigger já vive no header do card em mobile; o Sheet é
  portalizado — sem conflito.

## Métrica & gate (TDD)

Novo spec `apps/showcase/tests/e2e/responsive-modals.spec.ts` (escopo `demo`),
em `/admin/posts`, clicando o trigger "Actions" **visível** em cada viewport:

- **<768px (360, 640):** ao abrir, existe `[data-slot="sheet-content"]`
  ancorado embaixo (`bottom ≈ innerHeight`), **largura ≥90% da viewport**
  (`widthRatio ≥ 0.9`), e **cada item de ação ≥44px** de altura. Nenhum
  `[role="menu"]` (Dropdown) visível.
- **≥768px (768, 1024, 1440):** ao abrir, existe `[role="menu"]`; **nenhum**
  `sheet-content` visível (regressão desktop).
- **Mantém:** zero overflow horizontal nos 5 viewports com o sheet/menu aberto.

## Critérios de aceite

- [ ] `responsive-modals.spec.ts` verde nos 5 viewports.
- [ ] <768px: bottom-sheet de largura cheia (`widthRatio ≥ 0.9`), itens ≥44px.
- [ ] ≥768px: Dropdown intacto, sheet oculto (sem regressão desktop).
- [ ] Modais de gate (confirm/form) funcionam a partir de ambos os modos.
- [ ] Sheet com `SheetTitle` acessível (sem warning Radix).
- [ ] Baseline `@baseline` ainda 9/9.
- [ ] Suíte default verde; Vitest do `ui` verde (atenção ao dual-render sob
      JSDOM, como na Fase 2 — escopar queries ambíguas).
- [ ] biome + tsc limpos no pacote `ui`.
- [ ] CI limpo; PR squash-merged.

## Restrições (do projeto)

- Apenas shadcn primitives (`Sheet`, `DropdownMenu`) + cva + tokens OKLCH +
  utilitários Tailwind. Sem CSS ad-hoc; sem editar os primitives vendored
  (`sheet.tsx` / `dropdown-menu.tsx`) — o comportamento mobile vive no
  `ActionMenu.tsx`.
- Sem dependência nova (Sheet já existe; Radix Dialog já no projeto).
- Fix de componente compartilhado assere mobile-pass **e** no-desktop-regress.
- DCO `--signoff`; Conventional Commits (escopo `ui` p/ framework, `demo` p/
  showcase/test); subjects ≤100 chars; `--no-verify` só se o husky quebrado
  bloquear (após biome+test no container). v0.x tag é passo manual do mantenedor.
