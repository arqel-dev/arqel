# SKILL.md — @arqel-dev/devtools-extension

> Contexto canônico para AI agents trabalhando na extensão DevTools.

## Purpose

Extensão de browser (Chrome, Edge, Firefox) que adiciona uma aba **Arqel**
ao DevTools nativo, permitindo inspecionar admin panels Arqel em tempo
de execução. Endereça **RF-EC-02** e **RF-DX-09**.

Este pacote NÃO é publicado no npm (`private: true`). A distribuição final
acontece via Chrome Web Store + Firefox Add-ons após a Fase 4 fechar.

## Status

### Entregue (DEVTOOLS-001..005 + DEVTOOLS-008 polish)

- **Scaffold (DEVTOOLS-001):** estrutura multi-entry com Vite (`background`,
  `content-script`, `devtools`, `panel`). Manifest V3 para Chrome/Edge e
  manifest V3 com `browser_specific_settings` para Firefox 121+.
  Service worker stub que loga `onInstalled`. DevTools page que registra
  o painel "Arqel". Painel React com estados `Inactive` / `Connected`.
- **Detecção real (DEVTOOLS-002):** injeção do hook em `@arqel-dev/react` via
  `installDevToolsHook(version)`, gateada por `import.meta.env.DEV`.
  `installProbeBridge()` injeta um `<script>` page-world inline que
  dispara um `CustomEvent('arqel-devtools-probe')` lido pelo content
  script isolado. Resolve o problema do isolated world (MV3) sem expor
  APIs amplas. Background mantém `Map<tabId, {detected, version}>`
  per-tab e chama `chrome.action.setIcon` com paths configuráveis.
  `chrome.tabs.onRemoved` limpa estado ao fechar aba.
- **Inertia inspector base (DEVTOOLS-003):** painel renderiza version
  string detectada e estado de conexão; preparado para receber Inertia
  page object quando o runtime emitir.
- **Polish (DEVTOOLS-008):** SKILL canonical, install docs em
  `docs/devtools-extension/install.md`, suíte de coverage gaps cobrindo
  idempotência do bridge, lifecycle de tab, sanity de probe source,
  null-version handling e resiliência do painel a inputs estranhos.
- Suíte Vitest: 69 testes em 13 files (manifests, content-script,
  background, painel — Inertia inspector, Policy debugger, Fields,
  Time Travel, Performance Metrics, coverage gaps).
- **Policy debugger (DEVTOOLS-004):** nova top-tab "Policies" no
  painel renderiza a shared prop `__devtools.policyLog` que o
  `arqel-dev/core` emite em `app()->environment('local')`. Tabela com
  ability / arguments (JSON colapsado) / badge allow|deny / botão de
  expand/collapse para o stack trace por linha. Filter por result
  (`all|allow|deny`) + search por ability. Counter agregado no
  header (`X allowed / Y denied`). Empty state quando log vazio.
  Production builds sem `__devtools` ⇒ tab fica em empty state — sem
  leak. Captura via `@arqel-dev/react` no inertia-bridge: extrai
  `props['__devtools']` e expõe no hook como `getDevToolsPayload()`.

- **Field schema inspector (DEVTOOLS-005):** nova top-tab "Fields" no
  painel renderiza schema normalizado a partir de `pageProps`. Hook
  `@arqel-dev/react` ganha `getFieldsSchema()` com heurística defensive
  (`pageProps.fields` → `pageProps.resource.fields` →
  `pageProps.form.fields`), normaliza para `FieldSchema[]` (defaults
  `visible=true`, `required=false`). Painel: lista compacta com badge
  de tipo, search por name, filter por type (dropdown), counter
  `X visible / Y total`. Click expande detail view: validation rules
  como `<ul>`, `meta.dependsOn`, `meta.visibleWhen` JSON-stringify e
  `<JsonNode>` reusable para o meta completo. Empty state quando
  `fields=[]`.

- **Time-travel debugging (DEVTOOLS-006):** nova top-tab "Time Travel"
  consome o ring buffer de até 50 `NavigationSnapshot`s capturado pelo
  hook em `@arqel-dev/react`. Cada snapshot inclui `id` (único por
  navegação), `timestamp`, `url`, `pageProps` (full payload), `sharedProps`
  e `durationMs?`. Painel renderiza timeline com path + relative
  timestamp + duration badge (vermelho quando ≥100ms). Click expande
  detalhe via `<JsonNode>` reusable mostrando pageProps e sharedProps.
  Botão "Replay" dispara `chrome.runtime.sendMessage({ type:
  'arqel.replay' })` + `CustomEvent('arqel-devtools-replay')` para o
  painel — handler runtime-side fica para futuro.
- **Performance metrics dashboard (DEVTOOLS-007):** nova top-tab
  "Performance" renderiza dashboard com 4 tiles (LCP, INP/FID, CLS,
  navigation time). Color coding good / needs-improvement / poor com
  thresholds canônicos do Web Vitals. Hook `@arqel-dev/react` instala
  `PerformanceObserver` automaticamente em `createArqelApp` quando
  `'PerformanceObserver' in window`. SSR-safe: bail-out quando `window`
  ausente. Footer mostra `queryCount` + `memoryUsage` da shared prop
  `__devtools` server-side. Empty state quando nenhuma métrica
  capturada.

### Por chegar

- Submissão a Chrome Web Store e Firefox Add-ons.

## Conventions

- TypeScript strict (herda `tsconfig.base.json`); `exactOptionalPropertyTypes`
  ligado — passar `undefined` explicitamente em props falha.
- Manifest V3 obrigatório (Chrome Web Store rejeita V2 desde 2024).
- Cada entry roda em contexto distinto (`background`, `content`, `devtools`,
  `panel`); evite imports cruzados que assumam DOM em service worker.
- **Isolated world bridge:** content script roda num world separado da
  página. Para detectar `window.__ARQEL_DEVTOOLS_HOOK__` injete um
  `<script>` inline que dispara um `CustomEvent` capturado pelo content
  script. Fallback para CSP-strict pages: probe same-world (sempre
  retorna false, aceitável).
- Permissões mínimas no manifest: apenas `scripting` + `tabs`. Nada de
  `<all_urls>` em `host_permissions`.
- Build separado por browser em `dist/chrome/` e `dist/firefox/`.
- Logs internos usam `console.warn` (Biome bloqueia `console.log`).
- Nunca em produção: o hook só é exposto se o runtime detectar
  `import.meta.env.DEV`. Vite faz dead-code-elimination.

## Anti-patterns

- **Não** declare `host_permissions` amplas ou `<all_urls>` fora de
  `content_scripts.matches` — Chrome Web Store rejeita.
- **Não** exponha `window.__ARQEL_DEVTOOLS_HOOK__` em builds de produção;
  o runtime precisa gatear a injeção atrás de uma flag de ambiente.
- **Não** importe APIs do DOM dentro de `background.ts` — service workers
  em MV3 não têm `window`/`document`.
- **Não** publique este pacote no npm (`private: true`); distribuição é
  via stores de extensão.
- **Não** use polling com `setInterval` para detectar o hook sem cleanup
  — vaza memória e drena bateria. O modelo correto é probe único na
  injeção do content script + listener de `CustomEvent`.

## Examples

### Load unpacked em chrome://extensions

```bash
pnpm --filter @arqel-dev/devtools-extension build:chrome
# Chrome/Edge: chrome://extensions → Developer mode → Load unpacked
#   → selecione packages-js/devtools-extension/dist/chrome
```

Ver `docs/devtools-extension/install.md` para Firefox e troubleshooting.

### Detectar se app expõe Arqel

No console da página rodando uma app Arqel em DEV mode:

```js
window.__ARQEL_DEVTOOLS_HOOK__
// => { version: '0.10.0' } se @arqel-dev/react chamou installDevToolsHook
// => undefined em produção (DCE removeu o código)
```

### Inspecionar Inertia state (futuro DEVTOOLS-003+)

Atualmente o painel mostra apenas `Connected (vX.Y.Z)`. A inspeção de
props/page object chega em DEVTOOLS-003 quando `@arqel-dev/react` começar
a postar `arqel.inertia.update` para o background.

## Related

- `PLANNING/11-fase-4-ecossistema.md` §2 — DEVTOOLS-001..008
- `PLANNING/01-spec-tecnica.md` — RF-EC-02, RF-DX-09
- `PLANNING/03-adrs.md` — ADR-001 (Inertia-only), ADR-008 (testes obrigatórios)
- `docs/devtools-extension/install.md` — guia passo-a-passo de carga manual
