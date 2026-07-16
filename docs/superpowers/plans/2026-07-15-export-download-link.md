# Export Download Link Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire the export `download_url` from the PHP session flash through to a clickable "Baixar" link in the React success toast, fixing an end-to-end-broken export UX.

**Architecture:** The `ResourceController` already flashes `download_url` to the session, but the Inertia middleware doesn't serialize it. Add it to the serialized `flash` block, extend `FlashPayload`, and render it as a link in `FlashToast` (the `useFlash` hook already returns the whole payload, so no hook-logic change). Four disjoint layers: PHP core, TS types, React ui, plus i18n + an E2E spec.

**Tech Stack:** PHP 8.3+/Laravel/Inertia (core), TypeScript (types/hooks/ui), Pest (PHP tests), Vitest (JS tests), Playwright (E2E).

## Global Constraints

- `declare(strict_types=1);` in every PHP file.
- The React `<a>` uses `href={downloadUrl}` + `download` attr — NEVER `dangerouslySetInnerHTML`.
- `download_url` is a snake_case field in the PHP payload / `FlashPayload` / Inertia props; the React prop passed into `FlashToast` is camelCase `downloadUrl`.
- The download link uses `t('arqel.flash.download', 'Baixar')` — the i18n key lives in the PHP lang files under the `arqel.*` namespace; the fallback `'Baixar'` guarantees render.
- The link element carries `data-testid="flash-download-link"` (E2E selector).
- Do NOT touch `packages-js/ui/src/relations/**` (PR #377) or `packages-js/fields-js/**` (PR #378) — those are active parallel sessions.
- Do NOT change `useFlash`'s logic — it already does `return flash`; only the `FlashPayload` type covers the new field.
- Reuse the design system for the link style (shadcn/cva + OKLCH tokens); no ad-hoc CSS.
- Host limits: Vitest + Playwright do NOT run on the host (JS blocked) — CI is authoritative for hooks/ui/E2E. PHP (Pest) runs locally: `packages/core/vendor/bin/pest`.
- Before pushing: run `composer run lint` from repo ROOT (subagents commit `--no-verify`).
- Commits: Conventional Commits + DCO signoff (`--signoff`), `--no-verify`. Scopes: `core` (PHP/middleware), `types`, `hooks`, `ui`, `export`/`showcase` (E2E).
- Spec: `docs/superpowers/specs/2026-07-15-export-download-link-design.md`.

---

### Task 1: Serialize `download_url` in the Inertia flash (PHP) + type it

The PHP half + the type that lets it flow to React. The bug's root cause.

**Files:**
- Modify: `packages/core/src/Http/Middleware/HandleArqelInertiaRequests.php` (the `flash` block, ~lines 88-93)
- Modify: `packages-js/types/src/inertia.ts` (`FlashPayload`)
- Test: `packages/core/tests/Feature/Export/ExportFlashDownloadUrlTest.php` (create) — or nearest existing export/inertia feature test dir

**Interfaces:**
- Produces: `SharedProps.flash.download_url` — `string | null` in the serialized Inertia payload.
- Produces: `FlashPayload` gains `download_url?: string | null`.

- [ ] **Step 1: Write the failing PHP test**

First find the real assertion pattern for Inertia props in this repo:
```bash
grep -rln "assertInertia\|Assert::" packages/core/tests 2>/dev/null | head
grep -rn "download_url\|BulkExportRoundTrip" packages/export/tests 2>/dev/null | head
```
Model the new test on `packages/export/tests/Feature/BulkExportRoundTripTest.php` (which already sets up a bulk export) but assert at the **Inertia payload** level, not just the session. Create `packages/core/tests/Feature/Export/ExportFlashDownloadUrlTest.php`:

```php
<?php

declare(strict_types=1);

// Drive a bulk export through the ResourceController, then follow the
// redirect and assert the Inertia flash payload carries download_url.
// (Adapt the export setup — model, resource registration, export dir —
//  from BulkExportRoundTripTest.php, which already wires all of it.)

it('serializes download_url into the Inertia flash payload after a bulk export', function (): void {
    // ... arrange: a resource with an ExportAction, a few records, auth user
    //     (copy the arrange block from BulkExportRoundTripTest.php)

    // act: POST the bulk export action, then GET the redirect target as Inertia
    $response = $this->actingAs($user)
        ->post($bulkActionUrl, ['action' => 'export', 'ids' => $ids]);

    $response->assertRedirect();

    // The redirect flashes download_url; follow it and read the Inertia props.
    $follow = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get($response->headers->get('Location'));

    $props = $follow->json('props');
    expect($props['flash']['download_url'] ?? null)->not->toBeNull()
        ->and($props['flash']['download_url'])->toContain('/exports/');
});
```

Note to implementer: if driving the full bulk-export round-trip in one test is awkward, an acceptable alternative is a focused middleware test that puts `download_url` in the session (`session()->flash('download_url', '/admin/exports/abc/download')`) and asserts it appears in the serialized Inertia `flash` props. The REQUIRED assertion is: **`download_url` present in `props.flash`** — the mechanism to get it into the session can be the simpler `session()->flash(...)` if the full export arrange is too heavy.

- [ ] **Step 2: Run to verify it fails**

Run (from `packages/core/`): `vendor/bin/pest tests/Feature/Export/ExportFlashDownloadUrlTest.php`
Expected: FAIL — `props.flash.download_url` is null/absent (middleware doesn't serialize it).

- [ ] **Step 3: Serialize `download_url` in the middleware**

In `packages/core/src/Http/Middleware/HandleArqelInertiaRequests.php`, add the line to the `flash` array (after `warning`):

```php
'flash' => [
    'success' => fn () => $request->session()->get('success'),
    'error' => fn () => $request->session()->get('error'),
    'info' => fn () => $request->session()->get('info'),
    'warning' => fn () => $request->session()->get('warning'),
    'download_url' => fn () => $request->session()->get('download_url'),
],
```

- [ ] **Step 4: Extend `FlashPayload`**

In `packages-js/types/src/inertia.ts`, add the optional field to `FlashPayload`:

```typescript
export interface FlashPayload {
  success: string | null;
  error: string | null;
  info: string | null;
  warning: string | null;
  download_url?: string | null;
}
```

- [ ] **Step 5: Run to verify it passes**

Run (from `packages/core/`): `vendor/bin/pest tests/Feature/Export/ExportFlashDownloadUrlTest.php`
Expected: PASS. Then run the middleware's existing tests to confirm no regression: `vendor/bin/pest tests/Feature/Http` (or wherever `HandleArqelInertiaRequests` is tested — `grep -rl HandleArqelInertiaRequests packages/core/tests`).

- [ ] **Step 6: Commit**

```bash
git add packages/core/src/Http/Middleware/HandleArqelInertiaRequests.php packages/core/tests/Feature/Export packages-js/types/src/inertia.ts
git commit --no-verify --signoff -m "fix(core): serialize export download_url into the Inertia flash

ResourceController flashes download_url after a bulk export but the Inertia
middleware only serialized success/error/info/warning, so the URL never
reached React and the user had no way to download the file. Add download_url
to the serialized flash block + FlashPayload type.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Render the download link in `FlashToast` + i18n

The React half. `useFlash` already returns the payload (no change), so this is `FlashToast` + `FlashContainer` + the i18n key.

**Files:**
- Modify: `packages-js/ui/src/flash/FlashToast.tsx` (add `downloadUrl` prop + render)
- Modify: `packages-js/ui/src/flash/FlashContainer.tsx` (pass `flash.download_url` to the success toast)
- Modify: `packages/core/resources/lang/en/arqel.php` + `packages/core/resources/lang/pt_BR/arqel.php` (add `flash.download` key)
- Test: `packages-js/ui/tests/flash/FlashToast.test.tsx` (create or extend — check `ls packages-js/ui/tests/flash`)

**Interfaces:**
- Consumes: `FlashPayload.download_url` (Task 1); `t('arqel.flash.download', 'Baixar')`.
- Produces: `FlashToastProps` gains `downloadUrl?: string | null`; the rendered `<a data-testid="flash-download-link" href download>`.

- [ ] **Step 1: Confirm the i18n namespace + design-system link pattern**

```bash
grep -n "'flash'\|=> \[" packages/core/resources/lang/en/arqel.php | head
grep -rn "flash_dismiss\|arqel.flash\|arqel.aria" packages/core/resources/lang/en/arqel.php | head
grep -rn "buttonVariants\|cva\|cn(" packages-js/ui/src/flash/FlashToast.tsx packages-js/ui/src/button* 2>/dev/null | head
```
Use the exact namespace the file already uses (if `arqel.php` nests `flash => [...]`, the key is `arqel.flash.download`; match reality). For the link style, reuse the ui package's existing link/button class helper (the same `cn(...)`/`cva` the toast already imports) — no new CSS.

- [ ] **Step 2: Write the failing Vitest test**

Create/extend `packages-js/ui/tests/flash/FlashToast.test.tsx`:

```tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { FlashToast } from '../../src/flash/FlashToast.js';

describe('FlashToast download link', () => {
  it('renders a download link when downloadUrl is present', () => {
    render(<FlashToast kind="success" message="Export ready" downloadUrl="/admin/exports/abc/download" onDismiss={() => {}} />);
    const link = screen.getByTestId('flash-download-link');
    expect(link).toHaveAttribute('href', '/admin/exports/abc/download');
    expect(link).toHaveAttribute('download');
  });

  it('renders no download link when downloadUrl is absent or empty', () => {
    render(<FlashToast kind="success" message="Saved" onDismiss={() => {}} />);
    expect(screen.queryByTestId('flash-download-link')).toBeNull();
    render(<FlashToast kind="success" message="Saved" downloadUrl="" onDismiss={() => {}} />);
    expect(screen.queryByTestId('flash-download-link')).toBeNull();
  });
});
```

(Adjust the `FlashToast` required props — `onDismiss`, `durationMs` — to match its real signature; read `FlashToast.tsx:15-43` first.)

- [ ] **Step 3: Add the `downloadUrl` prop + render to `FlashToast`**

In `packages-js/ui/src/flash/FlashToast.tsx`, add to `FlashToastProps`:
```tsx
  downloadUrl?: string | null;
```
Destructure it in the component signature, and render after the message text (inside the toast body, before/after the dismiss button per the existing layout):
```tsx
{typeof downloadUrl === 'string' && downloadUrl !== '' && (
  <a
    href={downloadUrl}
    download
    data-testid="flash-download-link"
    className={/* reuse the ui link/button class helper confirmed in Step 1 */}
  >
    {t('arqel.flash.download', 'Baixar')}
  </a>
)}
```
`t` is already imported in this file (used at line ~68 for `flash_dismiss`).

- [ ] **Step 4: Pass `download_url` through `FlashContainer`**

In `packages-js/ui/src/flash/FlashContainer.tsx`, where it renders the `success` toast, pass the field:
```tsx
<FlashToast kind="success" message={/* existing */} downloadUrl={flash.download_url} /* ...existing props */ />
```
(Read `FlashContainer.tsx` first — it maps kinds to toasts; add `downloadUrl` only to the `success` branch. If it renders toasts via a loop over KINDS, pass `downloadUrl={kind === 'success' ? flash.download_url : undefined}`.)

- [ ] **Step 5: Add the i18n key**

In `packages/core/resources/lang/en/arqel.php` and `pt_BR/arqel.php`, add under the namespace confirmed in Step 1 (e.g. a `flash` sub-array):
- en: `'download' => 'Download',`
- pt_BR: `'download' => 'Baixar',`
(If `arqel.php` has no `flash` sub-array, add `'flash' => ['download' => ...]`. Match the file's existing structure/style.)

- [ ] **Step 6: Run the ui tests**

Vitest is blocked on the host (bin stub). Verify locally what you can with `node`/tsc typecheck if available; otherwise state that CI validates. Name the command CI runs: `pnpm --filter @arqel-dev/ui test`. Do a static self-check: the link renders only for non-empty string `downloadUrl`; `t` fallback is `'Baixar'`; `data-testid` matches the E2E selector.

- [ ] **Step 7: Commit**

```bash
git add packages-js/ui/src/flash packages-js/ui/tests/flash packages/core/resources/lang
git commit --no-verify --signoff -m "fix(ui): render export download link in the success FlashToast

FlashToast gains an optional downloadUrl prop and renders a 'Baixar' link
(<a download>, data-testid=flash-download-link) when present; FlashContainer
passes flash.download_url to the success toast. i18n key arqel.flash.download.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: E2E spec for the export → download flow

The end-to-end proof, on the showcase dogfood stack (CI Playwright).

**Files:**
- Create: `apps/showcase/tests/e2e/17-export.spec.ts`

**Interfaces:**
- Consumes: the `data-testid="flash-download-link"` from Task 2; the showcase `PostResource` `ExportAction` (bulk).

- [ ] **Step 1: Read the conventions**

```bash
sed -n '1,20p' apps/showcase/tests/e2e/fixtures.ts
sed -n '150,190p' apps/showcase/tests/e2e/05-actions.spec.ts   # the bulk-bar select pattern
```
Note the real selectors: header select-all is `thead input[type="checkbox"], thead [role="checkbox"]`; bulk actions are buttons in a bulk bar; `loggedInPage` fixture handles auth.

- [ ] **Step 2: Write the E2E spec**

Create `apps/showcase/tests/e2e/17-export.spec.ts`:

```ts
import { expect, test } from './fixtures';

/**
 * PostResource exposes a CSV ExportAction as a bulk action. Selecting rows
 * reveals the bulk-action bar; clicking Export runs the export and flashes a
 * download_url, which the success FlashToast renders as a "Baixar" link
 * (data-testid=flash-download-link). Clicking it downloads the CSV.
 */
test.describe('Export bulk action', () => {
  test('exports selected rows and offers a downloadable CSV via the flash link', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    // Enter bulk mode (select all) and trigger Export.
    await page.locator('thead input[type="checkbox"], thead [role="checkbox"]').first().click();
    await page.getByRole('button', { name: /export/i }).click();

    // The success toast surfaces the download link.
    const link = page.getByTestId('flash-download-link');
    await expect(link).toBeVisible();

    // Clicking it downloads a non-empty CSV.
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      link.click(),
    ]);
    const path = await download.path();
    expect(path).toBeTruthy();
    expect(download.suggestedFilename()).toMatch(/\.csv$/i);
  });
});
```

Note: if the Export button is inside a bulk-bar with a different accessible name, adjust the `getByRole` name (Step 1 shows the real label). If the download is served with `Content-Disposition: attachment` the `waitForEvent('download')` fires; if it opens inline, assert the link's `href` resolves to a 200 CSV via `page.request.get(await link.getAttribute('href'))` instead.

- [ ] **Step 3: Static-check the spec**

E2E runs only in CI (dogfood stack). Verify the selectors match Task 2's `data-testid` and the `05-actions` bulk-bar pattern. State that CI validates.

- [ ] **Step 4: Commit**

```bash
git add apps/showcase/tests/e2e/17-export.spec.ts
git commit --no-verify --signoff -m "test(showcase): E2E for export bulk action → downloadable CSV link

Drives select-all → Export → asserts the success FlashToast download link
(data-testid=flash-download-link) and that clicking it downloads a CSV.
Covers the previously-broken export UX end to end.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Follow-up security issue (ownership hardening)

Not code — a GitHub issue, so the deferred ownership check isn't lost.

**Files:** none (gh issue).

- [ ] **Step 1: Open the issue**

```bash
gh issue create --title "marketplace/export: ExportDownloadController lacks ownership check (IDOR between authenticated users)" --body "$(cat <<'BODY'
## Context
The export download route (`arqel.export.download`, `packages/export/routes/admin.php`) is `['web','auth']`-gated with a UUID route constraint `[a-f0-9-]+`, and `ExportDownloadController` documents (lines 18-27) that it does NOT enforce ownership — it globs `export-<uuid>.*` and serves the match.

## Risk
An authenticated user A can download user B's export if they know the (non-guessable, non-enumerable) UUID. Not "anyone", but not ownership-scoped either. Surfaced while wiring the export download link (fix: #<this PR>).

## Fix (already deferred in code as EXPORT-006/007/008)
- Back exports with an `Export` model carrying `owner_user_id` + `expires_at`.
- Sign the download URL and/or check `owner_user_id === auth()->id()` in the controller.
- Expire old export files.

Priority: medium (auth-gated + non-enumerable UUID mitigate; ownership is the real gap).
BODY
)"
```

---

## Self-Review

**1. Spec coverage:**
- Middleware serializes `download_url` → Task 1. ✅
- `FlashPayload` field → Task 1. ✅
- `useFlash` no-change (returns payload) → correctly NOT a task (Global Constraints notes it). ✅
- `FlashToast` link + `FlashContainer` pass-through → Task 2. ✅
- i18n `arqel.flash.download` → Task 2. ✅
- E2E export flow → Task 3. ✅
- Security follow-up issue → Task 4. ✅
- PHP test asserting payload (prova do bug) → Task 1 (with a simpler `session()->flash` fallback documented). ✅
- hooks Vitest test — the spec lists one, but since `useFlash` has ZERO logic change, a hooks test would only assert TypeScript typing (the field flows through `return flash` automatically). Folded into Task 1's type change + Task 2's ui test, which exercises the same field end-to-end. Not a separate task — noted here so the omission is deliberate, not a gap.

**2. Placeholder scan:** every code step has real code. The `/* reuse the ui link class */` and `/* existing */` markers are pointers to confirmed-in-Step-1 real values, not TBDs — each is preceded by a concrete `grep`/`sed` to resolve it. Acceptable because the exact class helper is repo-specific and must be read live.

**3. Type consistency:** `download_url` (snake: PHP payload, `FlashPayload`, `flash.download_url`) vs `downloadUrl` (camel: `FlashToastProps` prop) — used consistently and the snake→camel boundary is at `FlashContainer` (Task 2 Step 4). `data-testid="flash-download-link"` identical in Task 2 (render) and Task 3 (E2E selector). `t('arqel.flash.download', 'Baixar')` consistent between Task 2 render and Task 2 i18n key. ✅
