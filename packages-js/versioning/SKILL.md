# SKILL.md — arqel/versioning (JS)

## Purpose

Pacote React do Arqel Versioning. Entrega dois componentes
puramente apresentacionais consumidos pelos drawers/modais de
histórico de Resources:

- `<VersionTimeline>` — feed cronológico (`role="feed"`) das
  versões de um registro, com avatar/initials, summary, data
  relativa via `Intl.RelativeTimeFormat` e botões "View" /
  "Restore".
- `<VersionDiff>` — comparador side-by-side (`<dl role="region">`)
  de duas snapshots, com highlights por status
  (`added` / `removed` / `changed` / `unchanged`).

A fonte de dados canônica é o endpoint PHP
`GET /admin/{resource}/{id}/versions` do pacote `arqel/versioning`,
mas os componentes **não** sabem disso — recebem props prontas.

## Status

- **Versão atual**: `0.10.0-rc.1` (Fase 3, VERS-003 + VERS-004).
- Sem dependências runtime — só peer `react` (e `@inertiajs/react`
  declarado opcional, reservado para uso futuro num adapter).
- DevDeps de teste: `@testing-library/react`, `vitest`, `jsdom`.

## Conventions

- TypeScript strict (`noUncheckedIndexedAccess`,
  `exactOptionalPropertyTypes`). Sem `any`.
- SSR-safe: nada de `window`/`document` em render. `Intl` é
  guarded para fallback gracioso.
- Sem fetch/Inertia direto. Disparos saem por callbacks
  (`onViewDiff`, `onRestore`) — quem decide é o consumidor.
- Lint: Biome. Build: tsup. Testes: Vitest + Testing Library.
- Side-effects isolados em `dist/register.js` (vazio por enquanto)
  para preservar tree-shaking.

## Anti-patterns

- Fazer `router.post(...)` ou `fetch` dentro dos componentes —
  quebra SRP apresentacional. Use `onRestore`/`onViewDiff`.
- Misturar autorização: ela já chega resolvida via `canRestore` ou
  já filtrada upstream pelo PHP. Não chamar `Gate` no client.
- Usar bibliotecas pesadas de diff (jsdiff, diff-match-patch). O
  diff aqui é trivial linha-a-linha quando lengths batem; senão
  block-level "Modified". Se precisar de algoritmo melhor,
  ESCREVA um ADR primeiro.
- Renomear chaves públicas do `Version` type — o PHP serializa
  exatamente esse shape.

## Examples

```tsx
import { VersionTimeline, VersionDiff, type Version } from '@arqel/versioning';

function HistoryDrawer({ versions }: { versions: Version[] }) {
  const [active, setActive] = useState<Version | null>(null);

  return (
    <>
      <VersionTimeline
        versions={versions}
        onViewDiff={setActive}
        onRestore={(v) => router.post(`/admin/posts/1/versions/${v.id}/restore`)}
        canRestore={(v) => v.is_initial === false}
      />
      {active !== null ? (
        <VersionDiff
          before={active.attributes_before ?? {}}
          after={active.attributes_after ?? {}}
          fieldLabels={{ title: 'Título', body: 'Corpo' }}
        />
      ) : null}
    </>
  );
}
```

Diff só com mudanças (default):

```tsx
<VersionDiff before={{ a: 1, b: 2 }} after={{ a: 1, b: 3 }} />
// renderiza apenas a linha "b"
```

Diff incluindo unchanged:

```tsx
<VersionDiff before={{ a: 1, b: 2 }} after={{ a: 1, b: 3 }} showUnchanged />
```

## Related

- PHP package: `packages/versioning/` (Versionable trait,
  pruning, endpoint `versions`).
- Tickets: `PLANNING/10-fase-3-avancadas.md` → VERS-003 (slice
  React) e VERS-004 (diff viewer).
- Endpoint canônico: `GET /admin/{resource}/{id}/versions`.
