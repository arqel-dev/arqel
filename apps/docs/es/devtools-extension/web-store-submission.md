# Web Store Submission — Extensión Arqel DevTools

> **Estado:** preparación • **Versión target:** 0.8.0-rc.1 • **Paquete:** `@arqel-dev/devtools-extension` • **Stores:** Chrome Web Store, Firefox AMO, Microsoft Edge Add-ons

Este documento describe, **paso a paso**, cómo preparar y enviar la extensión Arqel DevTools a las tres stores principales. **No ejecutes las submissions todavía** — esta guía documenta el proceso para el release final v0.8.0 (no el `-rc.1`).

---

## 1. Visión general

`@arqel-dev/devtools-extension` es una extensión MV3 (Manifest V3) diseñada para Chrome, Firefox y Edge. Al ser una herramienta dev, su flujo de submission es simple — sin permisos amplios, sin llamadas de red, sin recolección de datos (ver `privacy-policy.md`).

| Store | URL | Costo | Review | Auto-update |
|---|---|---|---|---|
| Chrome Web Store | `chrome.google.com/webstore/devconsole` | $5 (única vez) | 1–3 días (hasta 7 con permisos amplios) | sí, vía manifest version |
| Firefox AMO | `addons.mozilla.org/developers` | gratis | 1–5 días (humano) | sí, vía manifest version |
| Microsoft Edge | `partner.microsoft.com/dashboard/microsoftedge` | gratis | 1–7 días | sí, vía manifest version |

---

## 2. Preparación de artefactos

### 2.1. Build local

Desde la raíz del monorepo:

```bash
pnpm --filter @arqel-dev/devtools-extension build
```

Eso produce, en `packages-js/devtools-extension/dist/`:

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

### 2.2. Validación del manifest

```bash
# Chrome/Edge
pnpx web-ext lint --source-dir=packages-js/devtools-extension/dist/chrome

# Firefox
pnpx web-ext lint --source-dir=packages-js/devtools-extension/dist/firefox
```

`web-ext lint` es la herramienta oficial de Mozilla — también válida para Chrome porque MV3 es esencialmente idéntico.

### 2.3. Smoke test manual

Antes de enviar, carga como extensión unpacked:

- Chrome: `chrome://extensions` → "Load unpacked" → apunta a `dist/chrome/`.
- Firefox: `about:debugging#/runtime/this-firefox` → "Load Temporary Add-on" → apunta a `dist/firefox/manifest.json`.
- Edge: `edge://extensions` → "Load unpacked" → apunta a `dist/chrome/`.

Verifica:

- [ ] El panel "Arqel" aparece en DevTools.
- [ ] En una página sin `__ARQEL_DEV__`, muestra "No Arqel application detected".
- [ ] En una página con Arqel en dev, lista resources y props.
- [ ] El tema light/dark funciona.

---

## 3. Chrome Web Store

### 3.1. Prerrequisitos de cuenta

1. Crea una cuenta de desarrollador en `chrome.google.com/webstore/devconsole`.
2. Paga **$5 USD** (única vez, no recurrente).
3. Verifica el email y habilita 2FA en la cuenta Google.
4. (Opcional) Configura un group publisher si quieres que toda la org `arqel` aparezca como publisher.

### 3.2. Campos requeridos del listing

| Campo | Valor |
|---|---|
| **Name** | `Arqel DevTools` |
| **Short description** (máx. 132 chars) | `Inspecione resources, fields e props Inertia das suas aplicações Arqel direto no DevTools do navegador.` |
| **Detailed description** | Markdown ~500–1500 palabras: qué es, cómo instalar (`pnpm add -D @arqel-dev/react/dev`), cómo habilitar (`window.__ARQEL_DEV__ = true`), tour de las pestañas, link a docs y GitHub. |
| **Category** | `Developer Tools` |
| **Language** | `Português (Brasil)` (primario) + `English` (secundario). |
| **Icon** | PNG 128×128 (en `apps/docs/public/devtools-icon-128.png`). |
| **Screenshots** | Mínimo **5**, resolución **1280×800** (preferida) o **640×400**. PNG sin transparencia. |
| **Promotional tile (small)** | PNG 440×280. |
| **Privacy policy URL** | `https://arqel.dev/devtools-extension/privacy-policy` (resuelve a `apps/docs/devtools-extension/privacy-policy.md`). |
| **Homepage URL** | `https://arqel.dev/devtools-extension`. |
| **Support URL** | `https://github.com/arqel-dev/arqel/issues`. |
| **Search keywords** | `arqel, laravel, inertia, devtools, react, admin panel, debugging, php`. |

### 3.3. Justificación de permisos

Para cada permiso del manifest, Chrome pide justificación cuando se dispara revisión humana:

- `devtools` → "Required to register a custom DevTools panel where users inspect Arqel-specific state."
- `storage` → "Stores user UI preferences (theme, last-active panel) locally. Never transmitted."
- `scripting` (si está presente) → "Injects a debugging bridge **only** in pages where the app explicitly opted in via `window.__ARQEL_DEV__ = true`. No-op in production."

### 3.4. Single purpose declaration

Chrome MV3 requiere un breve "single purpose statement":

> **"Provide debugging tools for developers building admin panels with the Arqel framework."**

### 3.5. Disclosures de uso de datos

Marca **cada** casilla como "**not collected**":

- [x] Información personalmente identificable — no recolectada
- [x] Información de salud — no recolectada
- [x] Información financiera — no recolectada
- [x] Información de autenticación — no recolectada
- [x] Comunicaciones personales — no recolectadas
- [x] Ubicación — no recolectada
- [x] Historial web — no recolectado
- [x] Actividad del usuario — no recolectada
- [x] Contenido del sitio — no recolectado

Y confirma:

- [x] "I do not sell user data to third parties."
- [x] "I do not use or transfer user data for purposes unrelated to my item's single purpose."
- [x] "I do not use or transfer user data to determine creditworthiness."

### 3.6. Timeline de review

- **Normal**: 1–3 días hábiles.
- **Extendido**: hasta 7 días si se dispara revisión humana (cualquier permiso "amplio" o primera submission de la cuenta).
- **Emergencia**: no hay fast-track en la Chrome Web Store.

---

## 4. Firefox Add-ons (AMO)

### 4.1. Cuenta

1. Crea una cuenta en `addons.mozilla.org`.
2. Verifica el email.
3. **Sin costo.**

### 4.2. Listed vs self-hosted

- **Listed** (recomendado): aparece en la búsqueda AMO, instala con 1-click, auto-update gestionado por Mozilla.
- **Self-hosted**: el usuario instala vía URL `.xpi`. Útil si quieres tu propio ciclo de updates sin review.

Para v0.8.0 → **listed**.

### 4.3. Source code obligatorio

AMO **exige source code** siempre que el bundle distribuido esté minificado/transpilado. Envía `dist/source.zip` conteniendo:

- `package.json` (con referencia al lockfile).
- `src/` (TypeScript original).
- `vite.config.ts`, `tsconfig.json`.
- `BUILD.md` con comandos exactos: `pnpm install --frozen-lockfile && pnpm build`.

Mozilla revisa manualmente — el build debe ser **bit-for-bit reproducible** desde el source enviado.

### 4.4. Campos del listing

Igual que Chrome con pequeñas diferencias:

- **Summary** (250 chars).
- **Description** (markdown, sin límite).
- **License**: selecciona `MIT License`.
- **Categories**: `Developer Tools` + `Web Development`.

### 4.5. Timeline de review

- **Listed**: 1–5 días hábiles (revisor humano).
- **Self-hosted (signing-only)**: minutos a horas (automático).

---

## 5. Microsoft Edge Add-ons

### 5.1. Cuenta

1. Visita `partner.microsoft.com/dashboard/microsoftedge`.
2. Inicia sesión con una cuenta Microsoft (personal o Azure AD).
3. Acepta los términos del partner program de Edge Add-ons.
4. **Sin costo.**

### 5.2. Submission

El proceso es **idéntico al de Chrome**: el mismo bundle `chrome.zip` funciona porque Edge es Chromium. Solo re-súbelo al dashboard Edge y copia/pega los campos del listing.

### 5.3. Diferencias

- Edge acepta hasta **10 screenshots** (vs 5 de Chrome) — recomendamos las mismas 5.
- Edge acepta upload de **video demo** (hasta 90s) — opcional pero recomendado.
- El review es más lento (hasta 7 días hábiles).

---

## 6. Update channel — cómo shippear updates sin re-review

Tras la primera aprobación, los updates **menores** (bump de versión, bug fix) **no disparan re-review humano** si:

1. **Manifest version sin cambios**: sigue MV3.
2. **Permisos inmutables**: no añadir/expandir permisos respecto a la versión anterior.
3. **Host permissions inmutables**: igual.
4. **Single purpose preservado**: no cambiar lo que la extensión hace.

Flujo:

```bash
# 1. Bump no package.json e manifest.json
# 2. Build
pnpm --filter @arqel-dev/devtools-extension build

# 3. Upload em cada dashboard
#    Chrome: dashboard → Package → Upload new package
#    Firefox: dashboard → Versions → Upload new version (com source.zip)
#    Edge: dashboard → Update → Upload new package
```

Las reviews automáticas aprueban en horas (Chrome/Edge) o ~1 día (Firefox).

**Cambios que SIEMPRE re-disparan review humana**:

- Nuevos `permissions` o `host_permissions`.
- Cambio en `single_purpose`.
- Cambio en la URL de `privacy_policy`.
- Cambio en funcionalidad core (ej., agregar una feature de red).

---

## 7. Plan de rollout v0.8.0

Cuando `v0.8.0-rc.1` sea promocionado a `v0.8.0` final:

1. [ ] Bump de versión coordinado: `package.json` + `manifest.json` (Chrome, Firefox, Edge) → `0.8.0`.
2. [ ] Build de artefactos: `pnpm --filter @arqel-dev/devtools-extension build`.
3. [ ] Smoke test local en Chrome, Firefox, Edge.
4. [ ] Submission a la Chrome Web Store (primera en entrar a review por ser la más rápida).
5. [ ] Submission a Edge Add-ons (mismo bundle).
6. [ ] Submission a Firefox AMO (con `source.zip`).
7. [ ] Esperar aprobaciones (en paralelo).
8. [ ] Anuncio en `CHANGELOG.md` y en las release notes de GitHub.
9. [ ] Actualizar `apps/docs/devtools-extension/install.md` con links a las tres stores.

---

## 8. Checklist final pre-submission

- [ ] `manifest.json` con version, name, description, icons todo completo.
- [ ] Política de privacidad publicada y URL públicamente accesible.
- [ ] 5+ screenshots 1280×800 listas en `apps/docs/public/devtools/screenshots/`.
- [ ] Promotional tile 440×280 lista.
- [ ] Descripción detallada revisada (PT-BR + EN).
- [ ] `source.zip` preparado para AMO con `BUILD.md`.
- [ ] Build reproducible verificado (`rm -rf dist && pnpm build` produce los mismos zips byte-por-byte).
- [ ] `web-ext lint` limpio de warnings.
- [ ] CSP estricto confirmado — sin `unsafe-eval`, sin `unsafe-inline`.
- [ ] Sin código `eval`, `Function()` o inyección dinámica de scripts.
- [ ] La política de privacidad enlaza de vuelta a docs e issues.

---

**Última revisión:** 2026-05-01 • **Owner:** mantenedores de Arqel.
