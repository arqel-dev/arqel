# SKILL.md — @arqel/a11y

## Purpose

Helpers de acessibilidade (WCAG 2.1 AA) para componentes React do ecossistema Arqel.
Foca em primitivos compartilhados: focus trap, live region/announcer, skip link e
visually-hidden. Reusados por `@arqel/ui`, `@arqel/auth`, `@arqel/ai`, e demais pacotes
React do monorepo.

## Key Contracts

- `useFocusTrap(active, { onEscape?, restoreFocus? })` — retorna `RefObject` para anexar ao
  container. Quando `active`, foca o primeiro elemento focável e cicla Tab/Shift+Tab.
- `useAnnounce()` — retorna `{ announce(message, priority?) }`. Cria/reutiliza live
  regions globais com IDs `arqel-a11y-live-polite` e `arqel-a11y-live-assertive`.
  SSR-safe (não toca `document` se ele não existir).
- `<SkipLink targetId label?>` — link visível em focus que move foco para landmark.
- `<VisuallyHidden as? hidden?>` — esconde visualmente, mantém em screen reader.
- `<LiveRegion message? priority? id?>` — region standalone com `aria-live`.

## Conventions

- **WCAG 2.1 AA** é o baseline. Componentes não devem regredir abaixo desse nível.
- Sempre testar com `vitest-axe` quando o componente renderiza markup acessível.
- Live regions usam clip-rect para esconder visualmente sem `display: none` (que remove de SR).
- Focus trap não deve assumir que o container tem elementos focáveis: define `tabindex=-1`
  como fallback para receber Escape.
- SSR: hooks só tocam `document` dentro de `useEffect` ou callbacks, nunca no top-level.

## Examples

```tsx
import { useFocusTrap, useAnnounce, SkipLink } from '@arqel/a11y';

function MyDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const ref = useFocusTrap<HTMLDivElement>(open, { onEscape: onClose });
  const { announce } = useAnnounce();

  return (
    <>
      <SkipLink targetId="main-content" />
      <div ref={ref} role="dialog" aria-modal="true">
        <button type="button" onClick={() => announce('Salvo!', 'polite')}>
          Salvar
        </button>
      </div>
    </>
  );
}
```

## Anti-patterns

- Não use `display: none` para esconder texto destinado a SR — use `<VisuallyHidden>`.
- Não remova `outline` global em `:focus` — preserve foco visível (use `:focus-visible`).
- Não use `tabindex` positivo (>0) — quebra ordem natural de tab.
- Não anuncie a mesma mensagem repetidamente sem reset — `useAnnounce` faz isso por você.
- Não trape foco sem fornecer um caminho de saída (Escape, botão de fechar).

## Related

- ADR-001 — Inertia-only (a11y não introduz fetch lib alguma)
- `apps/docs/guide/a11y.md` — guia canônico para contribuidores
- `packages-js/ui` — componentes que devem consumir esses helpers
- WAI-ARIA Authoring Practices: <https://www.w3.org/WAI/ARIA/apg/>
