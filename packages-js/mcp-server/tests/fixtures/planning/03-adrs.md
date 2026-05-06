# 03 — Architecture Decision Records (ADRs) (fixture)

> Fixture used by the parser and `get_adr` tool tests.

## Índice

| ADR | Título | Estado |
|---|---|---|
| ADR-001 | First decision | Accepted |
| ADR-002 | Second decision | Proposed |
| ADR-007 | Sparse decision (no status) | — |

---

## ADR-001: First decision

**Status:** Accepted • **Data:** 2026-01

### Contexto

This is the first ADR in the fixture. It mentions ```code fences``` to ensure
the parser does not break on them.

### Decisão

Pick option A.

```ts
// nested ## ADR-999 inside a fence must NOT be treated as a heading
const x = 1;
```

### Consequências

- Trade-off X.
- Trade-off Y.

---

## ADR-002: Second decision

**Estado:** Proposed • **Data:** 2026-02

### Contexto

Second ADR uses the PT-BR `Estado:` label. It also has a nested `### subheading`
that should be preserved verbatim in the body.

### Decisão

Pick option B.

---

## ADR-007: Sparse decision

### Contexto

This ADR intentionally omits a Status/Estado line so the parser must return
`status: null`.
