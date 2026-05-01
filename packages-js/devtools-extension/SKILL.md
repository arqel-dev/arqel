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

**Entregue (DEVTOOLS-002 — detecção real):**

- Injeção real do hook em `@arqel/react` via `installDevToolsHook(version)`,
  gateada por `import.meta.env.DEV` (nunca expõe `window.__ARQEL_DEVTOOLS_HOOK__`
  em builds de produção — Vite faz dead-code-elimination).
- `installProbeBridge()` injeta um `<script>` page-world inline que dispara um
  `CustomEvent('arqel-devtools-probe')` lido pelo content script isolado.
  Resolve o problema do isolated world (MV3) sem expor APIs amplas.
- Background mantém `Map<tabId, {detected, version}>` per-tab e chama
  `chrome.action.setIcon` com paths configuráveis (active vs inactive — por
  enquanto reutiliza o mesmo asset; TODO grayscale dedicado).
- `chrome.tabs.onRemoved` limpa estado quando a aba fecha.
- Mensagem renomeada `arqel.detect` → `arqel.detected` para refletir intent.
- Suite Vitest sobe para ≥21 asserts (5 em `content-script-detect`, 5 em
  `background-state`, mais cobertura existente).

**Entregue (DEVTOOLS-003 — Inertia state inspector):**

- Painel React `<InertiaInspector />` com 3 tabs (Page Props / Shared
  Props / Navigation History), filtro de busca compartilhado,
  highlighting via `<mark>` e botão "Copy" que escreve `JSON.stringify(state, null, 2)`
  no clipboard.
- Componente reutilizável `<JsonNode />` recursivo: primitivos inline,
  objetos/arrays expansíveis com toggle, busca por chave ou valor,
  marcação `data-match=yes|no` para CSS opcional.
- Background ganha `setTabArqelState` + `registerPanelPort` — relay
  `chrome.runtime.connect({name: 'arqel-devtools-panel'})` long-lived
  push do snapshot mais recente para cada panel conectado.
- Content script ganha `installStateRelay()` — injeta page-world script
  que escuta `CustomEvent('arqel-devtools-state-request')` e responde via
  `'arqel-devtools-state-response'`. Polling a cada 500ms, encaminha cada
  snapshot ao background como `{type: 'arqel.state', state}`.

**Por chegar:**

- DEVTOOLS-004 — Policy debugger (allow/deny por ação + Gate explain).
- Submissão a Chrome Web Store e Firefox Add-ons.

## Inertia state inspector (DEVTOOLS-003)

O painel não tem acesso direto ao `window` da página inspecionada (cada
contexto da extensão roda isolado). O fluxo de dados é:

```
[page world]                       [isolated content script]
window.__ARQEL_DEVTOOLS_HOOK__  →  CustomEvent state-response
                                          ↓
                                   chrome.runtime.sendMessage({type: 'arqel.state'})
                                          ↓
                                   [background service worker]
                                   setTabArqelState(tabId, state)
                                          ↓
                                   port.postMessage({type: 'arqel.state', state})
                                          ↓
                                   [DevTools panel]
                                   chrome.runtime.connect({name: 'arqel-devtools-panel'})
                                   useState(state) → re-render
```

Detalhes-chave:

- **Page-world script injection** continua sendo a única forma de ler o
  hook real do runtime; o relay reusa o mesmo padrão do
  `installProbeBridge` (DEVTOOLS-002).
- O panel envia `{type: 'arqel.panel.hello', tabId}` na primeira
  mensagem do port para que o background saiba para qual aba registrar
  o subscriber (DevTools panels não carregam `sender.tab.id` automaticamente).
- O background mantém um cache `Map<tabId, unknown>` e re-publica o
  snapshot atual para portas recém-conectadas, cobrindo o caso de o
  panel abrir depois da página já estar respondendo aos polls.
- Em testes (`MODE === 'test'`), tanto `installStateRelay` quanto o
  `runtime.onConnect` listener ficam ociosos — todos os fluxos têm
  exports puros para serem cobertos por Vitest sem `chrome` real.

## Hook injection — isolated world vs page world

Em Manifest V3, o content script roda num **isolated world**: comparte o DOM
com a página, mas o objeto `window` é diferente. Logo, `window.__ARQEL_DEVTOOLS_HOOK__`
escrito por `@arqel/react` é invisível para o content script.

A solução é a **page-world script injection**: o content script cria um
`<script>` inline com `textContent` que dispara um `CustomEvent` carregando o
resultado do probe. O event atravessa o DOM (compartilhado entre ambos
worlds) e é capturado pelo content script, que então faz `chrome.runtime
.sendMessage` para o service worker.

Trade-off: páginas com CSP restritivo bloqueiam inline scripts. Nesse caso
fazemos fallback para o probe same-world (que retorna `false`, aceitável —
a extensão simplesmente não detecta Arqel naquela página).

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
