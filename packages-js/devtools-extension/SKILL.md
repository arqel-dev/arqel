# SKILL.md — arqel/devtools-extension

## Purpose

Extensão de browser (Chrome, Edge, Firefox) que adiciona uma aba **Arqel** ao
DevTools nativo, permitindo inspecionar admin panels Arqel em tempo de execução.
Endereça **RF-EC-02** e **RF-DX-09**.

Este pacote NÃO é publicado no npm (`private: true`). A distribuição final
acontece via Chrome Web Store + Firefox Add-ons após a Fase 4 fechar.

## Status

**Entregue (DEVTOOLS-001 — scaffold):**

- Estrutura multi-entry com Vite (`background`, `content-script`, `devtools`,
  `panel`).
- Manifest V3 para Chrome/Edge e manifest V3 com `browser_specific_settings`
  para Firefox 121+.
- Service worker stub que loga `onInstalled` e ecoa mensagens recebidas.
- Content script com `detectArqel()` baseado em `window.__ARQEL_DEVTOOLS_HOOK__`.
- DevTools page que registra o painel "Arqel".
- Painel React com estados `Inactive` / `Connected (v{version})`.
- Vitest com 11+ asserts cobrindo manifests, content script, background e App.

**Por chegar:**

- DEVTOOLS-002 — detecção real do runtime + handshake bidirecional.
- DEVTOOLS-003 — Inertia inspector (props, page object, history).
- DEVTOOLS-004 — Policy debugger (allow/deny por ação + Gate explain).
- Submissão a Chrome Web Store e Firefox Add-ons.

## Conventions

- TypeScript strict (herda `tsconfig.base.json`).
- Cada entry roda em contexto distinto (`background`, `content`, `devtools`,
  `panel`); evite imports cruzados que assumam DOM em service worker.
- Permissões mínimas no manifest: apenas `scripting` + `tabs`. Nada de
  `<all_urls>` em `host_permissions`.
- Build separado por browser em `dist/chrome/` e `dist/firefox/`.
- Logs internos usam `console.warn` (Biome bloqueia `console.log`).

## Examples

Carregar manualmente em desenvolvimento:

```bash
pnpm --filter @arqel/devtools-extension build:chrome
# Chrome/Edge: chrome://extensions → Developer mode → Load unpacked
#   → selecione packages-js/devtools-extension/dist/chrome

pnpm --filter @arqel/devtools-extension build:firefox
# Firefox: about:debugging#/runtime/this-firefox → Load Temporary Add-on
#   → selecione packages-js/devtools-extension/dist/firefox/manifest.json
```

Para popular o painel sem o runtime real (DEVTOOLS-002):

```js
window.__ARQEL_DEVTOOLS_HOOK__ = { version: '0.10.0' };
```

## Anti-patterns

- **Não** declare `host_permissions` amplas ou `<all_urls>` fora de
  `content_scripts.matches` — Chrome Web Store rejeita.
- **Não** exponha `window.__ARQEL_DEVTOOLS_HOOK__` em builds de produção; o
  runtime precisa gatear a injeção atrás de uma flag de ambiente (DEVTOOLS-002).
- **Não** importe APIs do DOM dentro de `background.ts` — service workers em MV3
  não têm `window`/`document`.
- **Não** publique este pacote no npm (`private: true`); distribuição é via
  stores de extensão.

## Related

- `PLANNING/11-fase-4-ecossistema.md` §2 — DEVTOOLS-001..004
- `PLANNING/01-spec-tecnica.md` — RF-EC-02, RF-DX-09
- `PLANNING/03-adrs.md` — ADR-001 (Inertia-only), ADR-008 (testes obrigatórios)
