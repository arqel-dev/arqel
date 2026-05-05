# Web Store Submission — Arqel DevTools Extension

> **Status:** preparação • **Versão alvo:** 0.8.0-rc.1 • **Pacote:** `@arqel-dev/devtools-extension` • **Stores:** Chrome Web Store, Firefox AMO, Microsoft Edge Add-ons

Este documento descreve, **passo a passo**, como preparar e submeter a extensão Arqel DevTools às três principais lojas. **Não execute as submissões ainda** — este guia documenta o processo para a release final v0.8.0 (não a `-rc.1`).

---

## 1. Visão geral

A `@arqel-dev/devtools-extension` é uma extensão MV3 (Manifest V3) desenhada para Chrome, Firefox e Edge. Por ser ferramenta de dev, tem fluxo de submissão simples — sem permissions amplas, sem network calls, sem coleta de dados (ver `privacy-policy.md`).

| Loja | URL | Custo | Review | Auto-update |
|---|---|---|---|---|
| Chrome Web Store | `chrome.google.com/webstore/devconsole` | $5 (one-time) | 1–3 dias (até 7 com permissions amplas) | sim, via versão no manifest |
| Firefox AMO | `addons.mozilla.org/developers` | grátis | 1–5 dias (humano) | sim, via versão no manifest |
| Microsoft Edge | `partner.microsoft.com/dashboard/microsoftedge` | grátis | 1–7 dias | sim, via versão no manifest |

---

## 2. Preparação dos artifacts

### 2.1. Build local

A partir da raiz do monorepo:

```bash
pnpm --filter @arqel-dev/devtools-extension build
```

Isso produz, em `packages-js/devtools-extension/dist/`:

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

### 2.2. Validação de manifest

```bash
# Chrome/Edge
pnpx web-ext lint --source-dir=packages-js/devtools-extension/dist/chrome

# Firefox
pnpx web-ext lint --source-dir=packages-js/devtools-extension/dist/firefox
```

`web-ext lint` é a ferramenta oficial da Mozilla — também válida para Chrome porque MV3 é praticamente idêntico.

### 2.3. Smoke test manual

Antes de submeter, carregar como unpacked extension:

- Chrome: `chrome://extensions` → "Carregar sem compactação" → apontar para `dist/chrome/`.
- Firefox: `about:debugging#/runtime/this-firefox` → "Carregar add-on temporário" → apontar para `dist/firefox/manifest.json`.
- Edge: `edge://extensions` → "Carregar descompactado" → apontar para `dist/chrome/`.

Verificar:

- [ ] Painel "Arqel" aparece no DevTools.
- [ ] Em página sem `__ARQEL_DEV__`, mostra "Nenhuma aplicação Arqel detectada".
- [ ] Em página com Arqel em dev, lista resources e props.
- [ ] Tema escuro/claro funciona.

---

## 3. Chrome Web Store

### 3.1. Pré-requisitos da conta

1. Criar conta de desenvolvedor em `chrome.google.com/webstore/devconsole`.
2. Pagar **$5 USD** (taxa única, não recorrente).
3. Verificar email e ativar 2FA na conta Google.
4. (Opcional) Configurar group publisher se quiser que toda a org `arqel` apareça como publisher.

### 3.2. Campos obrigatórios da listing

| Campo | Valor |
|---|---|
| **Nome** | `Arqel DevTools` |
| **Short description** (132 chars max) | `Inspecione resources, fields e props Inertia das suas aplicações Arqel direto no DevTools do navegador.` |
| **Detailed description** | Markdown ~500–1500 palavras: o que é, como instalar (`pnpm add -D @arqel-dev/react/dev`), como ativar (`window.__ARQEL_DEV__ = true`), tour das abas, link para docs e GitHub. |
| **Category** | `Developer Tools` |
| **Language** | `Português (Brasil)` (primário) + `English` (secundário). |
| **Icon** | 128×128 PNG (em `apps/docs/public/devtools-icon-128.png`). |
| **Screenshots** | Mínimo **5**, resolução **1280×800** (preferida) ou **640×400**. PNG sem transparência. |
| **Promotional tile (small)** | 440×280 PNG. |
| **Privacy policy URL** | `https://arqel.dev/devtools-extension/privacy-policy` (resolve para `apps/docs/devtools-extension/privacy-policy.md`). |
| **Homepage URL** | `https://arqel.dev/devtools-extension`. |
| **Support URL** | `https://github.com/arqel-dev/arqel/issues`. |
| **Search keywords** | `arqel, laravel, inertia, devtools, react, admin panel, debugging, php`. |

### 3.3. Permissions justification

Para cada permission no manifest, o Chrome pede justificativa quando há review humano:

- `devtools` → "Required to register a custom DevTools panel where users inspect Arqel-specific state."
- `storage` → "Stores user UI preferences (theme, last-active panel) locally. Never transmitted."
- `scripting` (se presente) → "Injects a debugging bridge **only** in pages where the app explicitly opted in via `window.__ARQEL_DEV__ = true`. No-op in production."

### 3.4. Single purpose declaration

Chrome MV3 exige uma "single purpose statement" curta:

> **"Provide debugging tools for developers building admin panels with the Arqel framework."**

### 3.5. Data usage disclosures

Marcar **todas** as caixas como "**não coleta**":

- [x] Personally identifiable information — não coleta
- [x] Health information — não coleta
- [x] Financial information — não coleta
- [x] Authentication information — não coleta
- [x] Personal communications — não coleta
- [x] Location — não coleta
- [x] Web history — não coleta
- [x] User activity — não coleta
- [x] Website content — não coleta

E confirmar:

- [x] "I do not sell user data to third parties."
- [x] "I do not use or transfer user data for purposes unrelated to my item's single purpose."
- [x] "I do not use or transfer user data to determine creditworthiness."

### 3.6. Review timeline

- **Normal**: 1–3 dias úteis.
- **Estendido**: até 7 dias se review humano for triggered (qualquer permission "broad" ou primeira submissão da conta).
- **Emergência**: não há fast-track no Chrome Web Store.

---

## 4. Firefox Add-ons (AMO)

### 4.1. Conta

1. Criar conta em `addons.mozilla.org`.
2. Verificar email.
3. **Sem custo.**

### 4.2. Listed vs self-hosted

- **Listed** (recomendado): aparece na busca do AMO, instala-se com 1-click, auto-update gerenciado pela Mozilla.
- **Self-hosted**: usuário instala via URL `.xpi`. Útil se quiser ciclo de updates próprio sem review.

Para v0.8.0 → **listed**.

### 4.3. Source code obrigatório

A AMO **exige código fonte** sempre que o bundle distribuído é minificado/transpilado. Submeter `dist/source.zip` que contém:

- `package.json` (com lockfile referência).
- `src/` (TypeScript original).
- `vite.config.ts`, `tsconfig.json`.
- `BUILD.md` com comandos exatos: `pnpm install --frozen-lockfile && pnpm build`.

A Mozilla revisa manualmente — o build deve ser **reproduzível bit-a-bit** a partir do source submetido.

### 4.4. Listing fields

Iguais ao Chrome com pequenas diferenças:

- **Summary** (250 chars).
- **Description** (markdown sem limite).
- **License**: selecionar `MIT License`.
- **Categories**: `Developer Tools` + `Web Development`.

### 4.5. Review timeline

- **Listed**: 1–5 dias úteis (revisor humano).
- **Self-hosted (signing-only)**: minutos a horas (automático).

---

## 5. Microsoft Edge Add-ons

### 5.1. Conta

1. Acessar `partner.microsoft.com/dashboard/microsoftedge`.
2. Login com Microsoft account (pode ser pessoal ou Azure AD).
3. Aceitar termos de Edge Add-ons partner program.
4. **Sem custo.**

### 5.2. Submissão

O processo é **idêntico ao Chrome**: mesmo bundle `chrome.zip` funciona porque Edge é Chromium. Apenas re-uploadear na dashboard Edge e copiar/colar listing fields.

### 5.3. Diferenças

- Edge aceita até **10 screenshots** (vs 5 do Chrome) — recomendamos os mesmos 5.
- Edge aceita upload de **vídeo demo** (até 90s) — opcional mas recomendado.
- Review é mais lento (até 7 dias úteis).

---

## 6. Update channel — como shippar updates sem re-review

Após primeira aprovação, updates **menores** (bump de versão, fix de bug) **não disparam re-review humano** se:

1. **Manifest version permanence**: continuar em MV3.
2. **Permissions imutáveis**: não adicionar/expandir permissions vs versão anterior.
3. **Host permissions imutáveis**: idem.
4. **Single purpose preservado**: não mudar o que a extensão faz.

Fluxo:

```bash
# 1. Bump no package.json e manifest.json
# 2. Build
pnpm --filter @arqel-dev/devtools-extension build

# 3. Upload em cada dashboard
#    Chrome: dashboard → Package → Upload new package
#    Firefox: dashboard → Versions → Upload new version (com source.zip)
#    Edge: dashboard → Update → Upload new package
```

Reviews automatizados liberam em horas (Chrome/Edge) ou ~1 dia (Firefox).

**Mudanças que SEMPRE re-disparam human review**:

- Novo `permissions` ou `host_permissions`.
- Mudança de `single_purpose`.
- Mudança de `privacy_policy` URL.
- Mudança de funcionalidade core (e.g., adicionar feature de network).

---

## 7. Rollout plan v0.8.0

Quando `v0.8.0-rc.1` for promovida a `v0.8.0` final:

1. [ ] Bump de versão coordenado: `package.json` + `manifest.json` (Chrome, Firefox, Edge) → `0.8.0`.
2. [ ] Build artifacts: `pnpm --filter @arqel-dev/devtools-extension build`.
3. [ ] Smoke test local em Chrome, Firefox, Edge.
4. [ ] Submissão Chrome Web Store (primeira a entrar em review por ser mais rápida).
5. [ ] Submissão Edge Add-ons (mesmo bundle).
6. [ ] Submissão Firefox AMO (com `source.zip`).
7. [ ] Aguardar aprovações (paralelas).
8. [ ] Anunciar no `CHANGELOG.md` e em release notes do GitHub.
9. [ ] Atualizar `apps/docs/devtools-extension/install.md` com links para as três stores.

---

## 8. Checklist final pré-submissão

- [ ] `manifest.json` com versão, name, description, icons completos.
- [ ] Privacy policy publicada e URL acessível publicamente.
- [ ] 5+ screenshots 1280×800 prontos em `apps/docs/public/devtools/screenshots/`.
- [ ] Promotional tile 440×280 pronto.
- [ ] Detailed description revisada (PT-BR + EN).
- [ ] `source.zip` preparado para AMO com `BUILD.md`.
- [ ] Build reproduzível verificado (`rm -rf dist && pnpm build` produz mesmos zips byte-a-byte).
- [ ] Lint `web-ext lint` sem warnings.
- [ ] CSP estrita confirmada — sem `unsafe-eval`, sem `unsafe-inline`.
- [ ] Sem código `eval`, `Function()`, ou injeção dinâmica de scripts.
- [ ] Privacy policy linka de volta para docs e issues.

---

**Última revisão:** 2026-05-01 • **Responsável:** maintainers Arqel.
