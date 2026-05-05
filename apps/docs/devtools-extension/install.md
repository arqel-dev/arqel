# Manual install of the Arqel DevTools extension

> Guide for developers who want to run the extension locally before
> publication on the Chrome Web Store / Firefox Add-ons.

The `@arqel-dev/devtools-extension` extension is private (`private: true` in
`package.json`) and is not yet on the stores. To use it in DEV you must
build it locally and load it in "unpacked" / "temporary" mode.

## 1. Build

The extension uses Vite with separate per-browser builds. Artifacts land in
`packages-js/devtools-extension/dist/<browser>`.

```bash
# Na raiz do monorepo:
pnpm install
pnpm --filter @arqel-dev/devtools-extension build:chrome
pnpm --filter @arqel-dev/devtools-extension build:firefox
```

Scripts available in the package:

| Script           | When to use                                       |
|------------------|---------------------------------------------------|
| `build:chrome`   | Production Chrome/Edge bundle build.              |
| `build:firefox`  | Production Firefox bundle build.                  |
| `build`          | Runs both builds sequentially.                    |
| `dev`            | `vite build --watch --mode chrome` for iteration. |
| `test`           | Vitest suite (currently 36 tests).                |
| `typecheck`      | `tsc --noEmit` with `strict` + `exactOptional...`.|

## 2. Chrome / Edge — Load unpacked

1. Open `chrome://extensions/?loadUnpacked` (or `edge://extensions`).
2. Toggle **Developer mode** in the top-right corner.
3. Click **Load unpacked**.
4. Select the `packages-js/devtools-extension/dist/chrome` directory.
5. The **Arqel DevTools** entry appears in the list; the icon goes to the
   toolbar.
6. Open any page in an Arqel app running in DEV
   (`php artisan serve` + `pnpm dev`), open DevTools (F12), and look for
   the **Arqel** tab.

If you modify the code, run `build:chrome` again and click the **reload**
icon inside `chrome://extensions` — Chrome does not auto-reload unpacked
extensions.

## 3. Firefox — Load Temporary Add-on

1. Open `about:debugging#/runtime/this-firefox`.
2. Click **Load Temporary Add-on…**.
3. Select the
   `packages-js/devtools-extension/dist/firefox/manifest.json` file.
4. The extension stays loaded **only until you close Firefox** — that is
   by design of `about:debugging`. To persist, sign with
   `web-ext sign` or wait for AMO publication.
5. Open DevTools (F12) and look for the **Arqel** tab.

## 4. Troubleshooting

### "Hook not detected" / tab shows `Inactive`

- Confirm the app is running with `NODE_ENV=development` or
  `vite dev` — the `@arqel-dev/react` runtime only calls `installDevToolsHook`
  when `import.meta.env.DEV === true`. In production builds the code is
  eliminated via dead-code-elimination (intentional).
- Check the page console: `window.__ARQEL_DEVTOOLS_HOOK__`.
  If it is `undefined`, the hook was not installed — verify the
  `@arqel-dev/react` version (minimum 0.10.0).

### CSP errors in the console (`Refused to execute inline script…`)

- The extension injects an inline `<script>` to bridge between the
  isolated world (content script) and the page world (the real `window`).
  Pages with strict CSP (`script-src 'self'`) block this.
- Current behavior: silent fallback to a same-world probe that returns
  `detected: false`. The tab shows `Inactive`.
- Manual workaround: run the app without CSP in DEV, or add
  `'unsafe-inline'` only in the local environment.

### `Disconnected port` or `Could not establish connection`

- Common when you close the inspected tab before DevTools closes the
  channel. The background clears tab state via `chrome.tabs.onRemoved` —
  just reopen DevTools.
- If it persists, check in `chrome://extensions` whether the extension has
  access to the site ("On all sites" mode for the `http://localhost`
  protocol).

### The icon does not change when I enter an Arqel app

- DEVTOOLS-002 still uses the same asset for both the active and
  inactive states (TODO documented in `background.ts`). The panel is the
  source of truth; the icon will get a dedicated grayscale variant in a
  future release.

### Quick smoke test

In the extension's own DevTools (click **service worker** inside
`chrome://extensions`):

```js
chrome.runtime.sendMessage({ type: 'arqel.detected', detected: true, version: 'manual' });
```

The **Arqel** tab of any page's DevTools should update to
`Connected (vmanual)` if the wiring is correct.

## 5. References

- `SKILL.md` at the package root — conventions and architecture.
- `PLANNING/11-fase-4-ecossistema.md` — full DEVTOOLS-001..008 roadmap.
- `src/manifests/chrome.json` / `firefox.json` — source manifests.
- Issue tracker — open a bug with label `area:devtools`.
