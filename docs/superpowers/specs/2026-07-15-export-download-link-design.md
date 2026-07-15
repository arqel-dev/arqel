# Export Download Link — Design

**Data:** 2026-07-15
**Tipo:** fix (integração PHP↔React) • **Pacotes:** `arqel/core` (PHP), `@arqel-dev/types`, `@arqel-dev/hooks`, `@arqel-dev/ui`
**Origem:** hardening rumo a 1.0 (E2E de export) — que expôs um bug de integração real.

## Contexto & bug

O `ExportAction` (bulk action, ex: `PostResource` no showcase) roda um export e o
`ResourceController::bulkAction` flasha uma URL de download na sessão:

```php
$redirect = $redirect->with('download_url', $downloadUrl);   // ResourceController.php:266
```

Mas o middleware Inertia (`HandleArqelInertiaRequests`) serializa o bloco `flash` com
**apenas** `success/error/info/warning` (linhas 88-93) — **`download_url` não está na lista**.
Resultado: o `download_url` nunca chega ao front-end. O React (`useFlash` → `FlashPayload`
= só os 4 kinds) não o conhece, e **nenhum componente renderiza um link de download**.

**Impacto:** o export produz o CSV e flasha a URL, mas o usuário vê só o toast "export
concluído" — **sem nenhuma forma de baixar o arquivo pela UI**. Export quebrado
end-to-end na ótica do usuário. Clássico "documented-but-unwired" na fronteira PHP↔React.

**Por que passou:** o `BulkExportRoundTripTest` assere o round-trip só no nível de sessão
(`$session->get('download_url')`), não que o payload Inertia entrega ao front —
"tests-mask-integration-gaps".

## Decisões (do brainstorming)

1. **Abordagem A:** `download_url` vira campo opcional do `FlashPayload`; o link é
   renderizado no `FlashToast` do kind `success`. Reusa o pipeline de flash inteiro; menor
   mudança; link explícito (testável) vs auto-trigger frágil.
2. **Segurança:** a rota de download já é `['web', 'auth']`-gated + constraint UUID
   `[a-f0-9-]+` (`packages/export/routes/admin.php`). Falta apenas **ownership check**
   (usuário autenticado pode baixar export de outro se souber o UUID não-adivinhável) — o
   hardening (URL assinada + ownership + expiry, EXPORT-006/007/008) já está deferido no
   código. Registrado como **issue de follow-up**; não bloqueia este fix (risco atual =
   IDOR-entre-autenticados com UUID não-enumerável).

## Arquitetura — 4 camadas (todas disjuntas das sessões paralelas #377/#378)

| Camada | Arquivo | Mudança |
|---|---|---|
| PHP | `packages/core/src/Http/Middleware/HandleArqelInertiaRequests.php` | serializa `download_url` no bloco `flash` |
| Types | `packages-js/types/src/inertia.ts` | `FlashPayload` ganha `download_url?: string \| null` |
| Hook | `packages-js/hooks/src/useFlash.ts` | **nenhuma mudança de lógica** — `return flash` já repassa o campo; só o tipo o cobre |
| React | `packages-js/ui/src/flash/{FlashToast,FlashContainer}.tsx` | renderiza link "Baixar" no success toast quando `download_url` presente |
| i18n | lang files (`flash.download`) | chave nova "Baixar"/"Download"/"Descargar" |

Colisão: #377 → `ui/src/relations/`; #378 → `fields-js/`. Este fix → `ui/src/flash/` +
`hooks/` (só tipo) + `types/` + `core` PHP. **Sem sobreposição.**

## Camada PHP

`HandleArqelInertiaRequests.php`, bloco `flash` (linhas 88-93):

```php
'flash' => [
    'success' => fn () => $request->session()->get('success'),
    'error' => fn () => $request->session()->get('error'),
    'info' => fn () => $request->session()->get('info'),
    'warning' => fn () => $request->session()->get('warning'),
    'download_url' => fn () => $request->session()->get('download_url'),
],
```

- **One-shot:** lido da sessão e evapora no próximo request (flash nativo Laravel) — efêmero,
  não vaza entre navegações. Correto para um artefato de download.
- **Fonte confiável:** a URL vem de `resolveDownloadUrl()` (código do framework, rota nomeada
  `arqel.export.download`), não de input do usuário — sem vetor XSS/open-redirect. O React
  renderiza como `href` de `<a download>`, não `dangerouslySetInnerHTML`.

## Types + hook

`packages-js/types/src/inertia.ts`:

```typescript
export interface FlashPayload {
  success: string | null;
  error: string | null;
  info: string | null;
  warning: string | null;
  download_url?: string | null;   // artefato de download efêmero (ex: export CSV)
}
```

Opcional (`?`) para retrocompat — flashes não-export continuam válidos.

`useFlash.ts`: **zero mudança de lógica.** O hook faz `return flash` (o payload inteiro), e o
loop de `onMessage` é restrito a `KINDS = ['success','error','info','warning']`. Então:
- `download_url` flui automaticamente no retorno assim que o tipo o inclui.
- `download_url` **não** dispara `onMessage` (não é um `FlashKind`) — sem mudança necessária.
- `EMPTY_FLASH` (sem `download_url`) permanece válido (campo opcional ausente).

`FlashKind` permanece os 4 tipos; `download_url` é ortogonal.

## React render

`FlashToast.tsx` — nova prop opcional + render condicional:

```tsx
export interface FlashToastProps {
  kind: FlashKind;
  message: string;
  downloadUrl?: string | null;   // novo
  // ...props existentes
}
```

No corpo, após a `message`:

```tsx
{typeof downloadUrl === 'string' && downloadUrl !== '' && (
  <a href={downloadUrl} download className={/* link do design system */} data-testid="flash-download-link">
    {t('arqel.flash.download', 'Baixar')}
  </a>
)}
```

**i18n — mecanismo real (verificado):** o `FlashToast` já usa `t(key, fallback)` do
`@arqel-dev/i18n` (ex: `t('arqel.aria.flash_dismiss', 'Dismiss')` na linha 68). O `t()`
resolve do payload i18n que o middleware serializa a partir dos lang files PHP. Então a
chave `arqel.flash.download` deve ser adicionada aos lang files PHP
(`packages/core/resources/lang/{en,pt_BR}/arqel.php` — o namespace `arqel.*`), e o React a
consome com fallback `'Baixar'` (o fallback garante render mesmo antes de a chave existir).
Confirmar o namespace exato (`arqel.flash.*` vs outro) contra `arqel.php` na implementação.

`FlashContainer.tsx` — passa `download_url` ao toast do kind `success` (o export flasha
`success` + `download_url` juntos):

```tsx
<FlashToast kind="success" message={flash.success} downloadUrl={flash.download_url} />
```

Os toasts `error/info/warning` não recebem `downloadUrl`. `download_url` sem `success` (edge
improvável) não tem toast-host — aceitável (export sempre flasha `success` junto).

- **A11y:** link dentro do toast `role="status"` (success) — anunciado por screen-reader; attr
  `download` dá o hint ao browser.
- **Estilo:** design system (shadcn/cva + tokens OKLCH), não CSS ad-hoc.
- **i18n:** `flash.download` nos lang files existentes (pt-BR "Baixar", en "Download", es "Descargar").

## Testes

**E2E — `apps/showcase/tests/e2e/17-export.spec.ts` (novo, Playwright, roda na CI):**
1. Login → `/admin/posts`.
2. Selecionar linhas (header checkbox → bulk mode).
3. Clicar **Export** na bulk-action bar.
4. Assere o toast de sucesso **com o link "Baixar"** (`data-testid="flash-download-link"`) —
   hoje falharia (link nunca renderiza).
5. `page.waitForEvent('download')` ao clicar o link → validar CSV não-vazio com headers.
Segue a convenção dos specs existentes (`import { expect, test } from './fixtures'`,
`loggedInPage`, seletores role/testid).

**Unitários (3 camadas):**
- **PHP** (core, Pest — rodável localmente): export bulk → `SharedProps.flash.download_url`
  presente no payload Inertia. **Falha na baseline** (prova do bug).
- **hooks** (Vitest — CI): `useFlash` expõe `download_url`; não dispara `onMessage`;
  retrocompat sem o campo.
- **ui** (Vitest — CI): `FlashToast` renderiza o link com `downloadUrl`, não-renderiza sem;
  `FlashContainer` passa `download_url` ao success toast.

Host: Vitest/E2E bloqueados no host → CI valida (autoritativa). PHP roda com Pest local.

## Fora de escopo (issue de follow-up)

- **Ownership check + URL assinada + expiry** no `ExportDownloadController` (EXPORT-006/007/008,
  já deferido no código) — abrir GitHub issue documentando o IDOR-entre-autenticados.
- Form-modal para escolher formato/colunas no export (EXPORT-006).
- Queue-threshold para exports grandes (já parcialmente presente via `ProcessExportJob`).
