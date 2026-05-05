# Instalação manual da extensão Arqel DevTools

> Guia para desenvolvedores que querem rodar a extensão localmente antes
> da publicação na Chrome Web Store / Firefox Add-ons.

A extensão `@arqel-dev/devtools-extension` é privada (`private: true` no
`package.json`) e ainda não está nas stores. Para usá-la em DEV é
necessário fazer build local e carregar em modo "unpacked" / "temporary".

## 1. Build

A extensão usa Vite com builds separados por browser. Os artefatos saem
em `packages-js/devtools-extension/dist/<browser>`.

```bash
# Na raiz do monorepo:
pnpm install
pnpm --filter @arqel-dev/devtools-extension build:chrome
pnpm --filter @arqel-dev/devtools-extension build:firefox
```

Os scripts disponíveis no pacote:

| Script           | Quando usar                                       |
|------------------|---------------------------------------------------|
| `build:chrome`   | Build de produção do bundle Chrome/Edge.          |
| `build:firefox`  | Build de produção do bundle Firefox.              |
| `build`          | Roda os dois builds sequencialmente.              |
| `dev`            | `vite build --watch --mode chrome` para iteração. |
| `test`           | Suíte Vitest (atualmente 36 testes).              |
| `typecheck`      | `tsc --noEmit` com `strict` + `exactOptional...`. |

## 2. Chrome / Edge — Load unpacked

1. Abra `chrome://extensions/?loadUnpacked` (ou `edge://extensions`).
2. Ative o toggle **Developer mode** no canto superior direito.
3. Clique em **Load unpacked**.
4. Selecione o diretório `packages-js/devtools-extension/dist/chrome`.
5. A entrada **Arqel DevTools** aparece na lista; o ícone vai para a
   toolbar.
6. Abra qualquer página em uma app Arqel rodando em DEV
   (`php artisan serve` + `pnpm dev`), abra o DevTools (F12) e procure
   a aba **Arqel**.

Se você modificar o código, rode `build:chrome` novamente e clique no
ícone de **reload** dentro de `chrome://extensions` — o Chrome não
recarrega automaticamente para extensões unpacked.

## 3. Firefox — Load Temporary Add-on

1. Abra `about:debugging#/runtime/this-firefox`.
2. Clique em **Load Temporary Add-on…**.
3. Selecione o arquivo
   `packages-js/devtools-extension/dist/firefox/manifest.json`.
4. A extensão fica carregada **somente até fechar o Firefox** — é por
   design do `about:debugging`. Para persistir, assine via
   `web-ext sign` ou aguarde a publicação no AMO.
5. Abra DevTools (F12) e procure a aba **Arqel**.

## 4. Troubleshooting

### "Hook não detectado" / aba mostra `Inactive`

- Confirme que a app está rodando com `NODE_ENV=development` ou
  `vite dev` — o runtime `@arqel-dev/react` só chama `installDevToolsHook`
  quando `import.meta.env.DEV === true`. Em builds de produção o código
  é eliminado por dead-code-elimination (intencional).
- Verifique no console da página: `window.__ARQEL_DEVTOOLS_HOOK__`.
  Se for `undefined`, o hook não foi instalado — confira a versão do
  `@arqel-dev/react` (mínimo 0.10.0).

### CSP errors no console (`Refused to execute inline script…`)

- A extensão injeta um `<script>` inline para fazer bridge entre o
  isolated world (content script) e o page world (`window` real).
  Páginas com CSP estrita (`script-src 'self'`) bloqueiam isso.
- Comportamento atual: fallback silencioso para probe same-world,
  que retorna `detected: false`. A aba mostra `Inactive`.
- Workaround manual: rode a app sem CSP em DEV, ou adicione
  `'unsafe-inline'` apenas no ambiente local.

### `Disconnected port` ou `Could not establish connection`

- Comum quando você fecha a aba inspecionada antes do DevTools fechar
  o canal. O background limpa o tab state via `chrome.tabs.onRemoved` —
  basta reabrir o DevTools.
- Se persistir, verifique em `chrome://extensions` se a extensão tem
  acesso ao site (modo "On all sites" para o protocolo `http://localhost`).

### Ícone não muda quando entro numa app Arqel

- DEVTOOLS-002 ainda usa o mesmo asset para o estado active e
  inactive (TODO documentado em `background.ts`). O painel é a fonte
  de verdade; o ícone vai virar grayscale dedicado em release futura.

### Teste de fumaça rápido

No DevTools da própria extensão (clique em **service worker** dentro
de `chrome://extensions`):

```js
chrome.runtime.sendMessage({ type: 'arqel.detected', detected: true, version: 'manual' });
```

A aba **Arqel** do DevTools de qualquer página deve atualizar para
`Connected (vmanual)` se a wiring estiver correta.

## 5. Referências

- `SKILL.md` na raiz do pacote — convenções e arquitetura.
- `PLANNING/11-fase-4-ecossistema.md` — roadmap completo DEVTOOLS-001..008.
- `src/manifests/chrome.json` / `firefox.json` — manifests-fonte.
- Issue tracker — abrir bug com label `area:devtools`.
