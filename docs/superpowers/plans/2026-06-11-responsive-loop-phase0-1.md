# Responsive-Design Dogfood — Phase 0 (measurement infra) + Phase 1 (Forms) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the reusable Playwright responsiveness-measurement infra, run a baseline scan, then fix the Forms surface (the biggest gap) so form layouts reflow correctly across 5 viewports — all framework fixes via shadcn/Tailwind/cva with a mobile-pass + no-desktop-regression contract.

**Architecture:** A shared `responsive.ts` E2E helper measures the DOM (overflow-x, off-viewport content, touch-target size) across [360,640,768,1024,1440] using the existing dual-mode `loggedInPage` fixture + dogfood Docker stack. Form fixes land in `packages-js/ui` shared components (`FormGrid`, `FormTabs`) reusing the proven responsive-grid pattern from `DashboardGrid`. Each fix is TDD (RED at a mobile width → GREEN, desktop stays green).

**Tech Stack:** Playwright (E2E in `apps/showcase`), React 19 + Tailwind v4 + shadcn/Radix + `cva`, the dogfood Docker stack (pg18/redis/reverb/vite), JS validated in a clean container (host node_modules is root-owned/broken).

**Conventions (from the spec + project):**
- Commit scope `demo` for showcase/test changes (commitlint rejects `showcase`); the package name (`ui`) for framework fixes. DCO `--signoff`. Footer `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`. Subjects ≤100 chars.
- JS tooling (vitest/biome/typecheck/playwright build) does NOT run on the host — run in a clean container: `docker run --rm -v /home/diogo/PhpstormProjects/arqel:/repo:ro -w /work node:22-alpine sh -c 'cp -a /repo/. /work; find /work -name node_modules -maxdepth 3 -type d -exec rm -rf {} +; corepack enable; pnpm install --frozen-lockfile=false --config.confirm-modules-purge=false >/dev/null 2>&1; pnpm -r --filter "./packages-js/*" build >/dev/null 2>&1; <cmd>'`.
- E2E runs against the dogfood stack at `http://localhost:8090` with `APP_BASE_URL=http://localhost:8090 pnpm --filter @arqel-dev/showcase exec playwright test <spec>`. Rebuild fresh dist into the host + restart the vite container before measuring (the stale-dist lesson).
- `--no-verify` only when the broken husky hook blocks, after verifying biome (container) + the relevant tests. Use `git -C /home/diogo/PhpstormProjects/arqel <cmd>`.
- ZERO ad-hoc CSS / hand-rolled components — reuse shadcn primitives + `cva` + the existing OKLCH tokens + Tailwind responsive utilities. If a shadcn primitive is missing, add it via the ShadCN CLI.

---

## Phase 0 — Measurement infra + baseline

### Task 1: The `responsive.ts` E2E helper

**Files:**
- Create: `apps/showcase/tests/e2e/responsive.ts`
- (reference) `apps/showcase/tests/e2e/fixtures.ts` (the `loggedInPage` fixture + `expect`)

- [ ] **Step 1: Write the helper with the four measurement primitives**

Create `apps/showcase/tests/e2e/responsive.ts`:

```ts
import { expect, type Page } from '@playwright/test';

/** The 5 target viewports (px widths) the loop measures at. */
export const VIEWPORTS = [360, 640, 768, 1024, 1440] as const;
export type ViewportWidth = (typeof VIEWPORTS)[number];

/** The mobile widths where touch-target rules apply. */
export const MOBILE_WIDTHS: ViewportWidth[] = [360, 640];

/**
 * Set the viewport to `width` (height 900), wait for the layout to settle
 * (network idle + fonts loaded + a paint frame), then run `fn`.
 */
export async function atViewport(
  page: Page,
  width: ViewportWidth,
  fn: () => Promise<void>,
): Promise<void> {
  await page.setViewportSize({ width, height: 900 });
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => (document as Document).fonts?.ready);
  await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => r(null))));
  await fn();
}

/** Run `fn` at every target viewport, labelling failures by width. */
export async function forEachViewport(
  page: Page,
  fn: (width: ViewportWidth) => Promise<void>,
): Promise<void> {
  for (const width of VIEWPORTS) {
    await atViewport(page, width, () => fn(width));
  }
}

/**
 * Assert no horizontal overflow on the document (and optionally a selector):
 * `scrollWidth <= clientWidth + tolerance`. A 1px tolerance absorbs sub-pixel
 * rounding. This is the highest-signal responsiveness metric.
 */
export async function assertNoHorizontalOverflow(
  page: Page,
  selector = 'body',
  tolerance = 1,
): Promise<void> {
  const overflow = await page.evaluate(
    ({ sel, tol }) => {
      const el = sel === 'body' ? document.body : document.querySelector(sel);
      if (!el) return { found: false, scrollWidth: 0, clientWidth: 0 };
      return { found: true, scrollWidth: el.scrollWidth, clientWidth: el.clientWidth, tol };
    },
    { sel: selector, tol: tolerance },
  );
  expect(overflow.found, `selector "${selector}" not found`).toBe(true);
  expect(
    overflow.scrollWidth,
    `"${selector}" overflows horizontally: scrollWidth=${overflow.scrollWidth} > clientWidth=${overflow.clientWidth}`,
  ).toBeLessThanOrEqual(overflow.clientWidth + tolerance);
}

/**
 * Assert each matched element's bounding box is within [0, viewportWidth]:
 * nothing clipped off the right edge, nothing zero-width (collapsed/hidden-broken).
 */
export async function assertWithinViewport(page: Page, selector: string): Promise<void> {
  const vw = page.viewportSize()?.width ?? 0;
  const boxes = await page.locator(selector).evaluateAll((els) =>
    els.map((el) => {
      const r = el.getBoundingClientRect();
      return { left: r.left, right: r.right, width: r.width, height: r.height };
    }),
  );
  for (const [i, b] of boxes.entries()) {
    // Skip genuinely hidden elements (0x0 AND offscreen) — only flag visible-but-clipped.
    if (b.width === 0 && b.height === 0) continue;
    expect(b.right, `"${selector}"[${i}] clipped off the right edge (right=${b.right} > ${vw})`).toBeLessThanOrEqual(
      vw + 1,
    );
    expect(b.left, `"${selector}"[${i}] starts off the left edge (left=${b.left})`).toBeGreaterThanOrEqual(-1);
  }
}

/**
 * Assert every interactive element matched by `selector` has a touch target
 * of at least `minPx` on its smaller dimension. WCAG 2.5.5 / Apple HIG → 44px.
 * Skips elements that are not visible (0x0).
 */
export async function assertTouchTargets(
  page: Page,
  selector: string,
  minPx = 44,
): Promise<void> {
  const sizes = await page.locator(selector).evaluateAll((els) =>
    els
      .filter((el) => {
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0; // visible only
      })
      .map((el) => {
        const r = el.getBoundingClientRect();
        return { w: Math.round(r.width), h: Math.round(r.height), text: (el.textContent ?? '').trim().slice(0, 24) };
      }),
  );
  for (const s of sizes) {
    expect(
      Math.min(s.w, s.h),
      `touch target too small for "${selector}" ("${s.text}"): ${s.w}x${s.h} < ${minPx}px`,
    ).toBeGreaterThanOrEqual(minPx);
  }
}

/** Capture a full-page screenshot for human before/after review. */
export async function shot(page: Page, surface: string, width: number): Promise<void> {
  await page.screenshot({ path: `test-results/responsive/${surface}-${width}.png`, fullPage: true });
}
```

- [ ] **Step 2: Type-check the helper in a container**

Run (container — the helper is a `.ts` Playwright file, type-correct against `@playwright/test`):
```
docker run --rm -v /home/diogo/PhpstormProjects/arqel:/repo:ro -w /work node:22-alpine sh -c 'cp -a /repo/. /work; find /work -name node_modules -maxdepth 3 -type d -exec rm -rf {} +; corepack enable; pnpm install --frozen-lockfile=false --config.confirm-modules-purge=false >/dev/null 2>&1; pnpm exec biome check apps/showcase/tests/e2e/responsive.ts 2>&1 | tail -5'
```
Expected: `No fixes applied` (biome clean). If biome reformats, copy the fixed file back via `docker cp` from a named container.

- [ ] **Step 3: Commit**

```bash
git -C /home/diogo/PhpstormProjects/arqel add apps/showcase/tests/e2e/responsive.ts
git -C /home/diogo/PhpstormProjects/arqel commit --signoff --no-verify -m "$(cat <<'EOF'
test(demo): add the responsive E2E measurement helper (5 viewports, DOM metrics)
EOF
)"
```
(Append the Co-Authored-By footer.)

---

### Task 2: A tabs-free form route in the showcase (to isolate the Grid)

The existing PostResource form uses Tabs, which conflates the Grid and Tabs surfaces. Add a minimal form route that renders a multi-column Grid WITHOUT tabs, so the Grid reflow can be measured in isolation. Also confirm the existing PostResource create form is reachable for the Tabs measurement.

**Files:**
- Read: `apps/showcase/app/Arqel/Resources/PostResource.php` (the current `form()` with Tabs/Grid) + `apps/showcase/routes/web.php` (how routes register) + `apps/showcase/resources/js/Pages/` (the page glob).
- Create: `apps/showcase/resources/js/Pages/GridFormDemo.tsx` (a standalone page rendering a `FormRenderer` with a Grid layout schema, no tabs) + a route `GET /admin/grid-form-demo`.

- [ ] **Step 1: Read the existing form + page/route pattern**

Read `apps/showcase/resources/js/Pages/VersionsDemo.tsx` (the existing standalone demo page pattern — it's how a non-Resource page + route is wired) and `apps/showcase/routes/web.php` (note: an app `/admin/*` route must be registered in `AppServiceProvider::register()` to beat the greedy `admin/{resource}` wildcard — see issue #234; follow that exact pattern). Read how `FormRenderer` is imported + the `FormSchema`/`Grid` shape from `@arqel-dev/types/forms` (`GridProps.columns: number | Record<string,number>`).

- [ ] **Step 2: Create the GridFormDemo page**

Create `apps/showcase/resources/js/Pages/GridFormDemo.tsx` — a standalone page rendering a `FormRenderer` whose schema is a single `Grid` with `columns: { sm: 1, md: 2, lg: 3 }` containing ~6 text fields (so the responsive grid is observable: 1 col on phone, 2 on tablet, 3 on desktop). Use the real `FormRenderer` + `FormSchema` types (read `VersionsDemo.tsx` + the form types for the exact prop shape; build the schema inline with 6 `{kind:'field', name, type:'text'}` entries inside a `{kind:'grid', columns:{sm:1,md:2,lg:3}}` layout, plus matching `FieldSchema[]`). Render it inside `<main id="arqel-main">`. `data-testid="grid-form-demo"`.

- [ ] **Step 3: Register the route**

In `apps/showcase/app/Providers/AppServiceProvider.php` `register()` (the place that beats the resource wildcard, per #234), add:
```php
Route::get('/admin/grid-form-demo', static fn () => Inertia::render('GridFormDemo'))
    ->middleware(['web', 'auth'])
    ->name('showcase.grid-form-demo');
```
(Match the existing `versions-demo` registration in that file exactly — same imports, same group.)

- [ ] **Step 4: Rebuild dist + restart the stack vite, then smoke-check the route**

Rebuild fresh dist into the host (the showcase serves `@arqel-dev/*` from `dist/`), restart the vite container, reseed:
```
# rebuild dist in a clean container + extract to host (see the project's documented pattern):
docker run --rm -v /home/diogo/PhpstormProjects/arqel:/repo:ro -w /work node:22-alpine sh -c 'apk add --no-cache tar >/dev/null 2>&1; cp -a /repo/. /work; find /work -name node_modules -maxdepth 3 -type d -exec rm -rf {} +; corepack enable; pnpm install --frozen-lockfile=false --config.confirm-modules-purge=false >/dev/null 2>&1; pnpm -r --filter "./packages-js/*" build >/dev/null 2>&1; cd /work && tar cf - packages-js/*/dist' > /tmp/dist.tar
tar xf /tmp/dist.tar -C /home/diogo/PhpstormProjects/arqel
docker compose -p arqel-dogfood -f apps/showcase/compose.dogfood.yml restart vite
# wait for vite + app, then:
curl -s -o /dev/null -w '%{http_code}' http://localhost:8090/admin/grid-form-demo
```
Expected: `302` (redirect to login when unauthenticated) — i.e. the route exists (not 404).

- [ ] **Step 5: Commit**

```bash
git -C /home/diogo/PhpstormProjects/arqel add apps/showcase/resources/js/Pages/GridFormDemo.tsx apps/showcase/app/Providers/AppServiceProvider.php
git -C /home/diogo/PhpstormProjects/arqel commit --signoff --no-verify -m "test(demo): add a tabs-free Grid form demo route to isolate Grid reflow"
```
(Co-Authored-By footer. Use `--no-verify` only if husky blocks; the .tsx is type-correct against the form types.)

---

### Task 3: Baseline scan spec (records the starting defects — expected to FAIL)

This spec measures the 6 surfaces' current state. It is the loop's map. It is EXPECTED to fail (that's the point — it documents the gaps). Mark the known-failing assertions with `test.fixme`-free but grouped so the report is readable; we run it once to capture the baseline + screenshots, then the per-surface fixes flip them green.

**Files:**
- Create: `apps/showcase/tests/e2e/responsive-baseline.spec.ts`

- [ ] **Step 1: Write the baseline spec**

Create `apps/showcase/tests/e2e/responsive-baseline.spec.ts` covering the 6 surfaces at their showcase routes, using the helper. Each surface = one `test` that navigates, then `forEachViewport` runs `assertNoHorizontalOverflow(page)` + `shot(page, '<surface>', width)`. Routes: forms-grid `/admin/grid-form-demo`, forms-tabs `/admin/posts/create`, tables `/admin/posts`, shell `/admin/posts` (the chrome), modals `/admin/posts` (open the row Actions dropdown), dashboard `/admin/dashboard`, typography `/admin/posts` (headings). Use `{ loggedInPage }`. Example shape:

```ts
import { test } from './fixtures';
import { assertNoHorizontalOverflow, forEachViewport, shot } from './responsive';

test.describe('responsive baseline (records current gaps)', () => {
  test('forms — grid', async ({ loggedInPage: page }) => {
    await page.goto('/admin/grid-form-demo');
    await forEachViewport(page, async (w) => {
      await shot(page, 'forms-grid', w);
      await assertNoHorizontalOverflow(page);
    });
  });
  // ...one test per surface (forms-tabs, tables, shell, modals, dashboard) — same shape, different route.
});
```

- [ ] **Step 2: Run the baseline against the stack (capture failures + screenshots)**

Run:
```
cd /home/diogo/PhpstormProjects/arqel/apps/showcase && APP_BASE_URL=http://localhost:8090 pnpm --filter @arqel-dev/showcase exec playwright test responsive-baseline --reporter=line 2>&1 | tail -30
```
Expected: SOME tests FAIL (the gaps — forms-tabs/forms-grid overflow at 360, etc.). Screenshots land in `test-results/responsive/`. This is the baseline — do NOT fix here. Record which surfaces/widths failed (paste the output) — that's the defect map.

- [ ] **Step 3: Commit the baseline spec**

```bash
git -C /home/diogo/PhpstormProjects/arqel add apps/showcase/tests/e2e/responsive-baseline.spec.ts
git -C /home/diogo/PhpstormProjects/arqel commit --signoff --no-verify -m "test(demo): responsive baseline spec — records the current per-surface gaps"
```
(Co-Authored-By footer. NOTE: this spec is expected-red until the per-surface fixes land; it is NOT part of the green CI gate yet — the per-surface specs below are. Add a top-of-file comment saying so, and `test.describe.skip` it OR keep it out of the default run so CI stays green — decide: prefer `test.describe` with a `@baseline` tag excluded from CI via grep, documented in the file header.)

---

## Phase 1 — Forms

### Task 4: Make `FormGrid` apply its responsive column map (the core fix)

`FormGrid` accepts `columns: number | Record<string,number>` but only ever reads `columns['md']` and emits an inline `gridTemplateColumns: repeat(N,...)` — so `{sm:1,md:2,lg:3}` is NOT responsive. Fix it to emit responsive Tailwind grid classes, reusing the exact pattern `DashboardGrid.gridColsClass` already uses correctly.

**Files:**
- Modify: `packages-js/ui/src/form/FormGrid.tsx`
- Reference: `packages-js/ui/src/widgets/DashboardGrid.tsx:99-114` (`gridColsClass` + `BREAKPOINT_PREFIX`)
- Test: `packages-js/ui/tests/FormGrid.test.tsx` (create if absent — check `ls packages-js/ui/tests`)

- [ ] **Step 1: Write the failing unit test**

Create/extend `packages-js/ui/tests/FormGrid.test.tsx` (vitest + @testing-library, mirror an existing ui test like `FormRenderer.test.tsx`):

```tsx
import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { FormGrid } from '../src/form/FormGrid.js';

describe('FormGrid responsive columns', () => {
  it('emits responsive grid classes for a breakpoint map', () => {
    const { container } = render(
      <FormGrid config={{ columns: { sm: 1, md: 2, lg: 3 } }}>
        <div>a</div>
      </FormGrid>,
    );
    const grid = container.firstElementChild as HTMLElement;
    // base 1 col + responsive overrides — NOT an inline repeat(2)
    expect(grid.className).toContain('grid-cols-1');
    expect(grid.className).toContain('md:grid-cols-2');
    expect(grid.className).toContain('lg:grid-cols-3');
    // no inline gridTemplateColumns: repeat(...) anymore
    expect(grid.style.gridTemplateColumns).toBe('');
  });

  it('still supports a plain numeric column count', () => {
    const { container } = render(
      <FormGrid config={{ columns: 2 }}>
        <div>a</div>
      </FormGrid>,
    );
    const grid = container.firstElementChild as HTMLElement;
    expect(grid.className).toContain('grid-cols-2');
  });
});
```

- [ ] **Step 2: Run it — RED**

Run (container):
```
docker run --rm -v /home/diogo/PhpstormProjects/arqel:/repo:ro -w /work node:22-alpine sh -c 'cp -a /repo/. /work; find /work -name node_modules -maxdepth 3 -type d -exec rm -rf {} +; corepack enable; pnpm install --frozen-lockfile=false --config.confirm-modules-purge=false >/dev/null 2>&1; pnpm -r --filter "./packages-js/*" build >/dev/null 2>&1; pnpm --filter @arqel-dev/ui exec vitest run tests/FormGrid.test.tsx 2>&1 | tail -12'
```
Expected: FAIL — the current FormGrid emits an inline `repeat(2)` + `grid-cols-1` is absent.

- [ ] **Step 3: Implement the fix (reuse the DashboardGrid pattern)**

Modify `packages-js/ui/src/form/FormGrid.tsx` to compute a responsive class string instead of inline style. Add a `gridColsClass` mirroring `DashboardGrid`'s (base `grid-cols-1` + `sm:`/`md:`/`lg:`/`xl:`/`2xl:` overrides; clamp 1..12). Keep the `gap` (a `gap-4` default class, or the configured gap via a style only for gap — but PREFER a Tailwind gap class; if `config.gap` is a custom string keep it as an inline `gap` style, otherwise default to `gap-4`). Final:

```tsx
import type { GridProps } from '@arqel-dev/types/forms';
import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';

export interface FormGridProps {
  config: GridProps;
  children: ReactNode;
  className?: string;
}

const BP_PREFIX: Record<string, string> = { sm: 'sm:', md: 'md:', lg: 'lg:', xl: 'xl:', '2xl': '2xl:' };

function gridColsClass(columns: GridProps['columns']): string {
  if (typeof columns === 'number') {
    return `grid-cols-${Math.max(1, Math.min(12, columns))}`;
  }
  const parts = ['grid-cols-1'];
  for (const bp of ['sm', 'md', 'lg', 'xl', '2xl'] as const) {
    const value = columns[bp];
    if (typeof value === 'number') {
      parts.push(`${BP_PREFIX[bp]}grid-cols-${Math.max(1, Math.min(12, value))}`);
    }
  }
  return parts.join(' ');
}

export function FormGrid({ config, children, className }: FormGridProps) {
  const usesCustomGap = typeof config.gap === 'string';
  return (
    <div
      className={cn('grid', gridColsClass(config.columns), !usesCustomGap && 'gap-4', className)}
      style={usesCustomGap ? { gap: config.gap } : undefined}
    >
      {children}
    </div>
  );
}
```

- [ ] **Step 4: Run it — GREEN + full ui suite**

Run (container):
```
docker run --rm -v /home/diogo/PhpstormProjects/arqel:/repo:ro -w /work node:22-alpine sh -c 'cp -a /repo/. /work; find /work -name node_modules -maxdepth 3 -type d -exec rm -rf {} +; corepack enable; pnpm install --frozen-lockfile=false --config.confirm-modules-purge=false >/dev/null 2>&1; pnpm -r --filter "./packages-js/*" build >/dev/null 2>&1; pnpm --filter @arqel-dev/ui test 2>&1 | tail -8; pnpm --filter @arqel-dev/ui typecheck 2>&1 | tail -3'
```
Expected: FormGrid tests PASS; full ui suite PASS (no regression); typecheck clean. Note: `grid-cols-1`..`grid-cols-12` + `sm:`/`md:`/`lg:` must be in Tailwind's safelist or generated — since DashboardGrid already emits these classes and works, they're produced; if the showcase build later shows missing classes, add them to the Tailwind safelist (verify in Task 6).

- [ ] **Step 5: Commit**

```bash
git -C /home/diogo/PhpstormProjects/arqel add packages-js/ui/src/form/FormGrid.tsx packages-js/ui/tests/FormGrid.test.tsx
git -C /home/diogo/PhpstormProjects/arqel commit --signoff --no-verify -m "fix(ui): apply the FormGrid responsive column map instead of a fixed inline grid"
```
(Co-Authored-By footer; biome the 2 files in a container first.)

---

### Task 5: Make `FormTabs` mobile-friendly (scrollable tab bar)

`FormTabs` renders a `flex gap-1 border-b` tab bar that overflows on narrow screens. Make the tab bar horizontally scrollable on mobile (a shadcn/Tailwind pattern — `overflow-x-auto` + no-wrap + scroll-snap), preserving the desktop look and keyboard nav. Use the existing tab primitive's structure; do NOT hand-roll a new tabs component.

**Files:**
- Modify: `packages-js/ui/src/form/FormTabs.tsx`
- Test: `packages-js/ui/tests/FormTabs.test.tsx` (create/extend; check existing ui tab tests first)

- [ ] **Step 1: Read FormTabs + confirm the tab-bar markup**

Read `packages-js/ui/src/form/FormTabs.tsx` fully — note the tab-bar container className (`flex gap-1 border-b ...`) and the trigger buttons. Confirm whether a shadcn `Tabs` primitive exists in `packages-js/ui/src/components/ui/` (`ls packages-js/ui/src/components/ui | grep -i tab`). If a shadcn Tabs exists, prefer aligning to it; if FormTabs is already a bespoke-but-accessible impl, the minimal responsive fix is the scroll container (don't rewrite it).

- [ ] **Step 2: Write the failing test**

Extend `packages-js/ui/tests/FormTabs.test.tsx`: render FormTabs with ≥5 tabs and assert the tab-bar container has the horizontal-scroll classes (so it won't overflow the viewport — it scrolls instead of pushing the page wide):

```tsx
// assert the tab-list container is scrollable + nowrap (won't overflow the page)
const tablist = container.querySelector('[role="tablist"]') as HTMLElement;
expect(tablist.className).toContain('overflow-x-auto');
expect(tablist.className).toMatch(/flex-nowrap|whitespace-nowrap/);
```
(Adjust the selector to FormTabs' real tab-list element from Step 1.)

- [ ] **Step 3: Run it — RED**

Run (container): `pnpm --filter @arqel-dev/ui exec vitest run tests/FormTabs.test.tsx 2>&1 | tail -10`. Expected: FAIL (no `overflow-x-auto`).

- [ ] **Step 4: Implement — add the scroll container classes**

In `FormTabs.tsx`, on the tab-list element, add `overflow-x-auto` + `flex-nowrap` (and keep `gap-1 border-b`). Do NOT change the triggers' look or the keyboard handling. The triggers keep their padding (which already gives them ≥44px height — verify in the E2E touch-target check). Minimal change only.

- [ ] **Step 5: Run it — GREEN + full ui suite**

Run (container): `pnpm --filter @arqel-dev/ui test 2>&1 | tail -6`. Expected: FormTabs + full suite PASS.

- [ ] **Step 6: Commit**

```bash
git -C /home/diogo/PhpstormProjects/arqel add packages-js/ui/src/form/FormTabs.tsx packages-js/ui/tests/FormTabs.test.tsx
git -C /home/diogo/PhpstormProjects/arqel commit --signoff --no-verify -m "fix(ui): make the FormTabs tab bar horizontally scrollable on narrow screens"
```
(Co-Authored-By footer; biome first.)

---

### Task 6: Forms responsive E2E (the surface gate — mobile passes + desktop no-regression)

The TDD E2E for the Forms surface: encodes that the Grid form + Tabs form now reflow correctly across all 5 viewports AND desktop doesn't regress. This is the green CI gate for the Forms phase.

**Files:**
- Create: `apps/showcase/tests/e2e/responsive-forms.spec.ts`

- [ ] **Step 1: Rebuild fresh dist + restart the stack (serve the FormGrid/FormTabs fixes)**

Rebuild dist + restart vite (as in Task 2 Step 4) so the stack serves the fixed components. Reseed if needed.

- [ ] **Step 2: Write the forms responsive spec**

Create `apps/showcase/tests/e2e/responsive-forms.spec.ts`:

```ts
import { expect } from '@playwright/test';
import { test } from './fixtures';
import {
  assertNoHorizontalOverflow,
  assertTouchTargets,
  assertWithinViewport,
  atViewport,
  forEachViewport,
  shot,
} from './responsive';

test.describe('responsive — forms', () => {
  test('grid form reflows + no overflow across viewports', async ({ loggedInPage: page }) => {
    await page.goto('/admin/grid-form-demo');
    await forEachViewport(page, async (w) => {
      await shot(page, 'forms-grid', w);
      await assertNoHorizontalOverflow(page);
      await assertWithinViewport(page, '[data-arqel-field] input');
    });
    // reflow: 1 column at 360 (fields stack), multi-column at 1280 (desktop intact)
    await atViewport(page, 360, async () => {
      const cols = await page.locator('.grid').first().evaluate((el) =>
        getComputedStyle(el).gridTemplateColumns.split(' ').length,
      );
      expect(cols, 'grid should be 1 column at 360px').toBe(1);
    });
    await atViewport(page, 1440, async () => {
      const cols = await page.locator('.grid').first().evaluate((el) =>
        getComputedStyle(el).gridTemplateColumns.split(' ').length,
      );
      expect(cols, 'grid should be multi-column at 1440px (no desktop regression)').toBeGreaterThan(1);
    });
  });

  test('tabs form: tab bar scrolls, no page overflow, touch targets ok', async ({ loggedInPage: page }) => {
    await page.goto('/admin/posts/create');
    await forEachViewport(page, async (w) => {
      await shot(page, 'forms-tabs', w);
      await assertNoHorizontalOverflow(page); // tab bar scrolls, doesn't widen the page
    });
    await atViewport(page, 360, async () => {
      await assertTouchTargets(page, '[role="tab"]', 44);
    });
  });
});
```
(Adjust selectors to the real DOM: the field wrapper is `[data-arqel-field]`; the tab triggers are `[role="tab"]` — verify against the running app and fix if different.)

- [ ] **Step 3: Run it — should be GREEN (the fixes are in)**

Run:
```
cd /home/diogo/PhpstormProjects/arqel/apps/showcase && APP_BASE_URL=http://localhost:8090 pnpm --filter @arqel-dev/showcase exec playwright test responsive-forms --reporter=line 2>&1 | tail -25
```
Expected: PASS (grid 1-col@360, multi-col@1440; tabs scroll, no overflow, touch ok). If a selector mismatch fails it, fix the selector (not the assertion) and re-run. If a REAL responsiveness gap remains (e.g. a field still overflows at 360), that's a NEW defect → note it as a candidate for an additional fix in this phase. Paste the green result + confirm the before/after screenshots in `test-results/responsive/` show the reflow.

- [ ] **Step 4: Commit**

```bash
git -C /home/diogo/PhpstormProjects/arqel add apps/showcase/tests/e2e/responsive-forms.spec.ts
git -C /home/diogo/PhpstormProjects/arqel commit --signoff --no-verify -m "test(demo): responsive E2E gate for the forms surface (grid reflow + tab scroll)"
```
(Co-Authored-By footer.)

---

### Task 7: Wire the forms responsive spec into CI + open the Phase-1 PR

**Files:**
- (verify) `.github/workflows/ci.yml` — the showcase E2E job runs `pnpm --filter @arqel-dev/showcase exec playwright test` (the WHOLE suite), so the new `responsive-forms.spec.ts` is picked up automatically. The baseline spec must be EXCLUDED from the green run (Task 3 Step 3) so it doesn't red the gate.

- [ ] **Step 1: Confirm the CI E2E job runs the full playwright suite (no per-file allowlist)**

Read the `apps/showcase` E2E step in `.github/workflows/ci.yml`. Confirm it runs `pnpm --filter @arqel-dev/showcase exec playwright test` with no `<spec>` filter (so `responsive-forms.spec.ts` runs) AND that the baseline spec is excluded (its `describe` is skipped or tagged-out). If the baseline would run + red the gate, fix that (skip/tag it) and commit `test(demo): keep the responsive baseline out of the green CI gate`.

- [ ] **Step 2: Full PHP+JS sanity before PR**

Run the affected ui suite once more (container) + confirm biome/typecheck clean on all touched JS. PHP unaffected (no PHP changed in Phase 0/1). Paste the green results.

- [ ] **Step 3: Push + open the PR (via the curl --resolve workaround — GitHub API DNS is broken on this host)**

```bash
git -C /home/diogo/PhpstormProjects/arqel push -u origin round-design/responsive-loop
TOKEN=$(gh auth token)
# POST the PR via REST with --resolve (see the project's documented network workaround)
```
PR title: `feat: responsive loop Phase 0-1 — measurement infra + forms reflow`. Body: the measurement helper + the FormGrid/FormTabs fixes + the before/after screenshots note + "first surface of the responsive dogfood loop (spec: docs/superpowers/specs/2026-06-11-...)". Reference any issue(s) filed for the forms defects.

- [ ] **Step 4: After CI-clean, merge (rebase, via REST/--resolve) + sync main**

Mirror the Round-23 merge flow: poll CI via curl/--resolve until all checks green, then `PUT /pulls/N/merge` with `merge_method: rebase`, then `git checkout main && git pull --ff-only`.

---

## Self-review notes (for the implementer)

- The baseline spec (Task 3) is intentionally red — it's the defect map, NOT a gate. Keep it out of the green CI run.
- The Grid fix reuses `DashboardGrid.gridColsClass` verbatim in spirit — if you find the classes aren't generated by Tailwind in the showcase build, add `grid-cols-1..12` + the `sm:/md:/lg:` variants to the Tailwind safelist (DashboardGrid already relies on them, so they should exist — verify).
- Every JS commit: biome (container) + typecheck (container) + the ui suite green BEFORE `--no-verify`.
- Shared-component contract: the Grid/Tabs fixes affect EVERY form. The forms E2E asserts both mobile-pass (360) AND desktop-no-regression (1440). The full ui vitest + the full showcase E2E run in CI catch cross-regression.
- shadcn/tokens only: the fixes use Tailwind responsive utilities + the existing grid pattern — no new CSS files, no new components, no color/spacing invention.
- After Phase 1 merges, the next plan (Phase 2 — Typography) reuses `responsive.ts` + the same per-surface spec pattern.
