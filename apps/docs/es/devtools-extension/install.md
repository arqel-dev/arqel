# Instalación manual de la extensión Arqel DevTools

> Guía para desarrolladores que quieren ejecutar la extensión localmente antes de
> su publicación en la Chrome Web Store / Firefox Add-ons.

La extensión `@arqel-dev/devtools-extension` es privada (`private: true` en
`package.json`) y aún no está en las stores. Para usarla en DEV debes
construirla localmente y cargarla en modo "unpacked" / "temporary".

## 1. Build

La extensión usa Vite con builds separados por browser. Los artefactos aterrizan en
`packages-js/devtools-extension/dist/<browser>`.

```bash
# Na raiz do monorepo:
pnpm install
pnpm --filter @arqel-dev/devtools-extension build:chrome
pnpm --filter @arqel-dev/devtools-extension build:firefox
```

Scripts disponibles en el paquete:

| Script           | Cuándo usar                                       |
|------------------|---------------------------------------------------|
| `build:chrome`   | Build de bundle Chrome/Edge de producción.        |
| `build:firefox`  | Build de bundle Firefox de producción.            |
| `build`          | Ejecuta ambos builds secuencialmente.             |
| `dev`            | `vite build --watch --mode chrome` para iterar.   |
| `test`           | Suite Vitest (actualmente 36 tests).              |
| `typecheck`      | `tsc --noEmit` con `strict` + `exactOptional...`. |

## 2. Chrome / Edge — Load unpacked

1. Abre `chrome://extensions/?loadUnpacked` (o `edge://extensions`).
2. Activa **Developer mode** en la esquina superior derecha.
3. Haz click en **Load unpacked**.
4. Selecciona el directorio `packages-js/devtools-extension/dist/chrome`.
5. La entrada **Arqel DevTools** aparece en la lista; el ícono va a la
   toolbar.
6. Abre cualquier página de una app Arqel corriendo en DEV
   (`php artisan serve` + `pnpm dev`), abre DevTools (F12) y busca la
   pestaña **Arqel**.

Si modificas el código, ejecuta `build:chrome` de nuevo y haz click en el ícono de **reload**
dentro de `chrome://extensions` — Chrome no auto-recarga las extensiones unpacked.

## 3. Firefox — Load Temporary Add-on

1. Abre `about:debugging#/runtime/this-firefox`.
2. Haz click en **Load Temporary Add-on…**.
3. Selecciona el archivo
   `packages-js/devtools-extension/dist/firefox/manifest.json`.
4. La extensión queda cargada **solo hasta que cierres Firefox** — eso es
   por diseño de `about:debugging`. Para persistirla, firma con
   `web-ext sign` o espera la publicación en AMO.
5. Abre DevTools (F12) y busca la pestaña **Arqel**.

## 4. Troubleshooting

### "Hook not detected" / la pestaña muestra `Inactive`

- Confirma que la app esté corriendo con `NODE_ENV=development` o
  `vite dev` — el runtime `@arqel-dev/react` solo llama `installDevToolsHook`
  cuando `import.meta.env.DEV === true`. En builds de producción el código se
  elimina vía dead-code-elimination (intencional).
- Revisa la consola de la página: `window.__ARQEL_DEVTOOLS_HOOK__`.
  Si es `undefined`, el hook no fue instalado — verifica la versión
  de `@arqel-dev/react` (mínimo 0.10.0).

### Errores CSP en la consola (`Refused to execute inline script…`)

- La extensión inyecta un `<script>` inline para hacer puente entre el
  isolated world (content script) y el page world (el `window` real).
  Las páginas con CSP estricto (`script-src 'self'`) bloquean esto.
- Comportamiento actual: fallback silencioso a una probe en mismo world que devuelve
  `detected: false`. La pestaña muestra `Inactive`.
- Workaround manual: ejecuta la app sin CSP en DEV, o agrega
  `'unsafe-inline'` solo en el entorno local.

### `Disconnected port` o `Could not establish connection`

- Común cuando cierras la pestaña inspeccionada antes de que DevTools cierre el
  channel. El background limpia el estado de la pestaña vía `chrome.tabs.onRemoved` —
  basta con reabrir DevTools.
- Si persiste, revisa en `chrome://extensions` si la extensión tiene
  acceso al sitio (modo "On all sites" para el protocolo `http://localhost`).

### El ícono no cambia cuando entro a una app Arqel

- DEVTOOLS-002 sigue usando el mismo asset para los estados activo e
  inactivo (TODO documentado en `background.ts`). El panel es la
  fuente de verdad; el ícono recibirá una variante grayscale dedicada en una
  release futura.

### Smoke test rápido

En las propias DevTools de la extensión (haz click en **service worker** dentro de
`chrome://extensions`):

```js
chrome.runtime.sendMessage({ type: 'arqel.detected', detected: true, version: 'manual' });
```

La pestaña **Arqel** de las DevTools de cualquier página debería actualizarse a
`Connected (vmanual)` si el cableado está correcto.

## 5. Referencias

- `SKILL.md` en la raíz del paquete — convenciones y arquitectura.
- `PLANNING/11-fase-4-ecossistema.md` — roadmap completo DEVTOOLS-001..008.
- `src/manifests/chrome.json` / `firefox.json` — manifests fuente.
- Issue tracker — abre un bug con label `area:devtools`.
