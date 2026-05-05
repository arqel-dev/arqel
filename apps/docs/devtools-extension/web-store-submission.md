# Web Store Submission — Arqel DevTools Extension

> **Status:** preparation • **Target version:** 0.8.0-rc.1 • **Package:** `@arqel-dev/devtools-extension` • **Stores:** Chrome Web Store, Firefox AMO, Microsoft Edge Add-ons

This document describes, **step by step**, how to prepare and submit the Arqel DevTools extension to the three main stores. **Do not run the submissions yet** — this guide documents the process for the v0.8.0 final release (not the `-rc.1`).

---

## 1. Overview

`@arqel-dev/devtools-extension` is an MV3 (Manifest V3) extension designed for Chrome, Firefox, and Edge. Being a dev tool, its submission flow is simple — no broad permissions, no network calls, no data collection (see `privacy-policy.md`).

| Store | URL | Cost | Review | Auto-update |
|---|---|---|---|---|
| Chrome Web Store | `chrome.google.com/webstore/devconsole` | $5 (one-time) | 1–3 days (up to 7 with broad permissions) | yes, via manifest version |
| Firefox AMO | `addons.mozilla.org/developers` | free | 1–5 days (human) | yes, via manifest version |
| Microsoft Edge | `partner.microsoft.com/dashboard/microsoftedge` | free | 1–7 days | yes, via manifest version |

---

## 2. Artifact preparation

### 2.1. Local build

From the monorepo root:

```bash
pnpm --filter @arqel-dev/devtools-extension build
```

That produces, in `packages-js/devtools-extension/dist/`:

```
dist/
├── chrome.zip       # bundle MV3 para Chrome/Edge
├── firefox.zip      # bundle MV3 para Firefox (manifest_version=3)
├── source.zip       # source code para AMO review (obrigatório)
└── manifests/
    ├── chrome.json
    ├── firefox.json
    └── edge.json
```

### 2.2. Manifest validation

```bash
# Chrome/Edge
pnpx web-ext lint --source-dir=packages-js/devtools-extension/dist/chrome

# Firefox
pnpx web-ext lint --source-dir=packages-js/devtools-extension/dist/firefox
```

`web-ext lint` is Mozilla's official tool — also valid for Chrome because MV3 is essentially identical.

### 2.3. Manual smoke test

Before submitting, load as an unpacked extension:

- Chrome: `chrome://extensions` → "Load unpacked" → point to `dist/chrome/`.
- Firefox: `about:debugging#/runtime/this-firefox` → "Load Temporary Add-on" → point to `dist/firefox/manifest.json`.
- Edge: `edge://extensions` → "Load unpacked" → point to `dist/chrome/`.

Verify:

- [ ] The "Arqel" panel shows in DevTools.
- [ ] On a page without `__ARQEL_DEV__`, it shows "No Arqel application detected".
- [ ] On a page with Arqel in dev, it lists resources and props.
- [ ] Light/dark theme works.

---

## 3. Chrome Web Store

### 3.1. Account prerequisites

1. Create a developer account at `chrome.google.com/webstore/devconsole`.
2. Pay **$5 USD** (one-time fee, not recurring).
3. Verify email and enable 2FA on the Google account.
4. (Optional) Configure a group publisher if you want the entire `arqel` org to appear as publisher.

### 3.2. Required listing fields

| Field | Value |
|---|---|
| **Name** | `Arqel DevTools` |
| **Short description** (132 chars max) | `Inspecione resources, fields e props Inertia das suas aplicações Arqel direto no DevTools do navegador.` |
| **Detailed description** | Markdown ~500–1500 words: what it is, how to install (`pnpm add -D @arqel-dev/react/dev`), how to enable (`window.__ARQEL_DEV__ = true`), tour of the tabs, link to docs and GitHub. |
| **Category** | `Developer Tools` |
| **Language** | `Português (Brasil)` (primary) + `English` (secondary). |
| **Icon** | 128×128 PNG (in `apps/docs/public/devtools-icon-128.png`). |
| **Screenshots** | Minimum **5**, resolution **1280×800** (preferred) or **640×400**. PNG without transparency. |
| **Promotional tile (small)** | 440×280 PNG. |
| **Privacy policy URL** | `https://arqel.dev/devtools-extension/privacy-policy` (resolves to `apps/docs/devtools-extension/privacy-policy.md`). |
| **Homepage URL** | `https://arqel.dev/devtools-extension`. |
| **Support URL** | `https://github.com/arqel-dev/arqel/issues`. |
| **Search keywords** | `arqel, laravel, inertia, devtools, react, admin panel, debugging, php`. |

### 3.3. Permissions justification

For every permission in the manifest, Chrome asks for justification when human review is triggered:

- `devtools` → "Required to register a custom DevTools panel where users inspect Arqel-specific state."
- `storage` → "Stores user UI preferences (theme, last-active panel) locally. Never transmitted."
- `scripting` (if present) → "Injects a debugging bridge **only** in pages where the app explicitly opted in via `window.__ARQEL_DEV__ = true`. No-op in production."

### 3.4. Single purpose declaration

Chrome MV3 requires a short "single purpose statement":

> **"Provide debugging tools for developers building admin panels with the Arqel framework."**

### 3.5. Data usage disclosures

Mark **every** box as "**not collected**":

- [x] Personally identifiable information — not collected
- [x] Health information — not collected
- [x] Financial information — not collected
- [x] Authentication information — not collected
- [x] Personal communications — not collected
- [x] Location — not collected
- [x] Web history — not collected
- [x] User activity — not collected
- [x] Website content — not collected

And confirm:

- [x] "I do not sell user data to third parties."
- [x] "I do not use or transfer user data for purposes unrelated to my item's single purpose."
- [x] "I do not use or transfer user data to determine creditworthiness."

### 3.6. Review timeline

- **Normal**: 1–3 business days.
- **Extended**: up to 7 days if human review is triggered (any "broad" permission or first submission from the account).
- **Emergency**: there is no fast-track on the Chrome Web Store.

---

## 4. Firefox Add-ons (AMO)

### 4.1. Account

1. Create an account at `addons.mozilla.org`.
2. Verify email.
3. **No cost.**

### 4.2. Listed vs self-hosted

- **Listed** (recommended): appears in AMO search, installs with 1-click, auto-update managed by Mozilla.
- **Self-hosted**: user installs via `.xpi` URL. Useful if you want your own update cycle without review.

For v0.8.0 → **listed**.

### 4.3. Mandatory source code

AMO **requires source code** whenever the distributed bundle is minified/transpiled. Submit `dist/source.zip` containing:

- `package.json` (with lockfile reference).
- `src/` (original TypeScript).
- `vite.config.ts`, `tsconfig.json`.
- `BUILD.md` with exact commands: `pnpm install --frozen-lockfile && pnpm build`.

Mozilla reviews manually — the build must be **bit-for-bit reproducible** from the submitted source.

### 4.4. Listing fields

Same as Chrome with minor differences:

- **Summary** (250 chars).
- **Description** (markdown, no limit).
- **License**: select `MIT License`.
- **Categories**: `Developer Tools` + `Web Development`.

### 4.5. Review timeline

- **Listed**: 1–5 business days (human reviewer).
- **Self-hosted (signing-only)**: minutes to hours (automatic).

---

## 5. Microsoft Edge Add-ons

### 5.1. Account

1. Visit `partner.microsoft.com/dashboard/microsoftedge`.
2. Sign in with a Microsoft account (personal or Azure AD).
3. Accept the Edge Add-ons partner program terms.
4. **No cost.**

### 5.2. Submission

The process is **identical to Chrome**: the same `chrome.zip` bundle works because Edge is Chromium. Just re-upload to the Edge dashboard and copy/paste the listing fields.

### 5.3. Differences

- Edge accepts up to **10 screenshots** (vs Chrome's 5) — we recommend the same 5.
- Edge accepts a **demo video** upload (up to 90s) — optional but recommended.
- Review is slower (up to 7 business days).

---

## 6. Update channel — how to ship updates without re-review

After the first approval, **minor** updates (version bump, bug fix) **do not trigger human re-review** if:

1. **Manifest version unchanged**: stays MV3.
2. **Immutable permissions**: do not add/expand permissions vs the previous version.
3. **Immutable host permissions**: same.
4. **Single purpose preserved**: do not change what the extension does.

Flow:

```bash
# 1. Bump no package.json e manifest.json
# 2. Build
pnpm --filter @arqel-dev/devtools-extension build

# 3. Upload em cada dashboard
#    Chrome: dashboard → Package → Upload new package
#    Firefox: dashboard → Versions → Upload new version (com source.zip)
#    Edge: dashboard → Update → Upload new package
```

Automated reviews approve in hours (Chrome/Edge) or ~1 day (Firefox).

**Changes that ALWAYS retrigger human review**:

- New `permissions` or `host_permissions`.
- Change to `single_purpose`.
- Change to the `privacy_policy` URL.
- Change to core functionality (e.g., adding a network feature).

---

## 7. Rollout plan v0.8.0

When `v0.8.0-rc.1` is promoted to `v0.8.0` final:

1. [ ] Coordinated version bump: `package.json` + `manifest.json` (Chrome, Firefox, Edge) → `0.8.0`.
2. [ ] Build artifacts: `pnpm --filter @arqel-dev/devtools-extension build`.
3. [ ] Local smoke test on Chrome, Firefox, Edge.
4. [ ] Submit to the Chrome Web Store (first to enter review since it is the fastest).
5. [ ] Submit to Edge Add-ons (same bundle).
6. [ ] Submit to Firefox AMO (with `source.zip`).
7. [ ] Wait for approvals (in parallel).
8. [ ] Announce in `CHANGELOG.md` and in GitHub release notes.
9. [ ] Update `apps/docs/devtools-extension/install.md` with links to the three stores.

---

## 8. Final pre-submission checklist

- [ ] `manifest.json` with version, name, description, icons all complete.
- [ ] Privacy policy published and URL publicly reachable.
- [ ] 5+ screenshots 1280×800 ready in `apps/docs/public/devtools/screenshots/`.
- [ ] Promotional tile 440×280 ready.
- [ ] Detailed description reviewed (PT-BR + EN).
- [ ] `source.zip` prepared for AMO with `BUILD.md`.
- [ ] Reproducible build verified (`rm -rf dist && pnpm build` produces the same zips byte-for-byte).
- [ ] `web-ext lint` clean of warnings.
- [ ] Strict CSP confirmed — no `unsafe-eval`, no `unsafe-inline`.
- [ ] No `eval`, `Function()`, or dynamic script-injection code.
- [ ] Privacy policy links back to docs and issues.

---

**Last revised:** 2026-05-01 • **Owner:** Arqel maintainers.
