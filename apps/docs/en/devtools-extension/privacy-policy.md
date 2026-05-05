# Privacy Policy — Arqel DevTools Extension

> **Status:** in force • **Version:** 1.0 • **Effective date:** 2026-05-01 • **Applies to:** `@arqel-dev/devtools-extension` (Chrome, Firefox, Edge)

The **Arqel DevTools** extension was designed with privacy-first as a core principle. This policy describes, transparently, exactly what the extension does with user data — the short answer is: **nothing leaves the user's machine, under any circumstances, without an explicit action.**

---

## 1. Executive summary

| Question | Answer |
|---|---|
| Does the extension collect personal data? | **No.** |
| Does the extension send data to external servers? | **No.** |
| Does the extension use analytics, telemetry, or crash reporting? | **No.** |
| Does the extension read cookies from other sites? | **No.** |
| Does the extension read browsing history? | **No.** |
| Does the extension work offline? | **Yes.** Fully local. |
| Where is data stored? | Only in the browser's own `chrome.storage.local`, on the user's device. |

---

## 2. What the extension does

`@arqel-dev/devtools-extension` is a debugging tool for Arqel developers. It adds a panel to the browser's DevTools that shows:

- **Detected resources** on the current page (list of Resource classes registered in the active Arqel panel).
- **Current Inertia props** and Inertia navigation history (active tab only, dev mode only).
- **React DevTools bridge events** for Arqel components (Field, Form, Table).
- **Performance markers** from Arqel hooks (`useArqelForm`, `useArqelTable`).

All of this comes **from the inspected page itself**, via a hook injected **only when** `window.__ARQEL_DEV__ === true` — a flag that is only set when the application is in `development` mode and the developer explicitly imports `@arqel-dev/react/dev`.

---

## 3. What the extension does NOT do

- **Does not collect** any personal data: name, email, IP, user-agent, geolocation, cookies, tokens, passwords — nothing.
- **Does not send** HTTP requests to Arqel servers or third parties. **Zero network calls** by default.
- **Does not use** Google Analytics, Mixpanel, Sentry, PostHog, or any telemetry tool.
- **Does not access** tabs that do not have the `__ARQEL_DEV__` flag set.
- **Does not persist** data across sessions beyond what the user explicitly "favorited" for inspection (and even that lives in `chrome.storage.local`, never remote).
- **Does not read** the contents of other installed extensions.
- **Does not modify** the DOM of the inspected page (read-only).

---

## 4. Permissions requested in the manifest

| Permission | Justification |
|---|---|
| `devtools` | Required to register a custom panel in DevTools. |
| `storage` | Saves local preferences (theme, active panel) via `chrome.storage.local`. |
| `scripting` (optional) | Injects the hook bridge **only** in pages with the `__ARQEL_DEV__` flag. |

The extension **does not request**: `tabs`, `history`, `cookies`, `webRequest`, `<all_urls>` host permissions, `nativeMessaging`, `identity`, or any permission that grants cross-origin content access.

---

## 5. DEV-only mode — layered protection

The bridge injection only happens when **all** of the following are true:

1. The application imported `@arqel-dev/react/dev` (separate subpath, removed from the production bundle via tree-shaking).
2. `process.env.NODE_ENV === 'development'`.
3. `window.__ARQEL_DEV__ === true` (set explicitly by the developer).

In **production**, even with the extension installed, it stays inert — the DevTools panel shows the message "No Arqel application detected on this tab".

---

## 6. Data sharing

**The extension does not share data.** There are no third parties, no embedded analytics SDK, no "anonymized usage statistics opt-in". The model is binary: **zero collection**.

If a future version ever considers adding optional telemetry (e.g., to understand most-used features), we will:

- Announce it in the changelog **before** release.
- Make it explicitly opt-in (default: off).
- Update this policy with a new version and effective date.
- Clearly document the collected fields.

Today this **does not exist and is not planned for v0.8.x**.

---

## 7. Cookies, local storage, and tracking

The extension **does not use cookies**. The only storage is `chrome.storage.local`, containing only:

- `arqel_devtools_theme` — `"light" | "dark"` (visual preference).
- `arqel_devtools_active_panel` — string identifying the last-opened tab.

This data **never leaves the device**.

---

## 8. Security

- The extension is open-source (MIT) — code auditable at `https://github.com/arqel-dev/arqel/tree/main/packages-js/devtools-extension`.
- Builds are reproducible via `pnpm build`.
- Source maps ship with the Web Store package, satisfying Mozilla AMO's reviewable-code requirement when minification is involved.
- No runtime dependencies — only React (already present in the DevTools host).

---

## 9. How to remove the extension

At any time, with no data loss (since there is no data):

- **Chrome/Edge**: `chrome://extensions` → find "Arqel DevTools" → "Remove".
- **Firefox**: `about:addons` → "Extensions" → gear next to "Arqel DevTools" → "Remove".

The extension's `chrome.storage.local` is wiped automatically by the browser on uninstall.

---

## 10. Children

The extension is a professional development tool. It is not directed at children under 13 and does not collect data that could identify age.

---

## 11. Changes to this policy

Any material change will be published in:

- This file (with a version bump and a new effective date).
- `CHANGELOG.md` at the monorepo root.
- Web Store release notes.

The previous version is preserved through git history.

---

## 12. Contact

- **Issues**: `https://github.com/arqel-dev/arqel/issues`
- **Email**: `security@arqel.dev` (privacy/security questions only).
- **Responsible disclosure**: see `SECURITY.md` at the monorepo root.

---

**Last revised:** 2026-05-01 • **Next planned revision:** with the v0.9.0 release or upon a material change.
