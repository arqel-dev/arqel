# @arqel-dev/devtools-extension

Extensão de browser que adiciona uma aba **Arqel** ao DevTools de Chrome,
Edge e Firefox para inspeção runtime de admin panels Arqel.

> Pacote privado — não publicado no npm. A distribuição final acontece via
> Chrome Web Store e Firefox Add-ons.

## Build

```bash
pnpm --filter @arqel-dev/devtools-extension build:chrome
pnpm --filter @arqel-dev/devtools-extension build:firefox
```

Os artefatos vão para `dist/chrome/` e `dist/firefox/`.

## Estado atual

Entregue (DEVTOOLS-001..008). O painel inclui os inspetores de Inertia, Fields,
Policy, Time Travel e Performance (`InertiaInspector`, `FieldsInspector`,
`PolicyDebugger`, `TimeTravel`, `PerformanceMetrics`). Veja a seção Status do
`SKILL.md` para o detalhe.
