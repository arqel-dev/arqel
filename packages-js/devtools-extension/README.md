# @arqel/devtools-extension

Extensão de browser que adiciona uma aba **Arqel** ao DevTools de Chrome,
Edge e Firefox para inspeção runtime de admin panels Arqel.

> Pacote privado — não publicado no npm. A distribuição final acontece via
> Chrome Web Store e Firefox Add-ons.

## Build

```bash
pnpm --filter @arqel/devtools-extension build:chrome
pnpm --filter @arqel/devtools-extension build:firefox
```

Os artefatos vão para `dist/chrome/` e `dist/firefox/`.

## Estado atual

Scaffold (DEVTOOLS-001). Veja `SKILL.md` para o que está entregue e o que está
por vir nos tickets DEVTOOLS-002..004.
