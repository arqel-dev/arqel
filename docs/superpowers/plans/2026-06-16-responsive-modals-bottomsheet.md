# Action Menu Bottom-Sheet (Mobile) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Under 768px, the collapsed row-actions menu becomes a full-width bottom Sheet (≥44px items) instead of the fixed 192px desktop Dropdown popper, while desktop keeps the Dropdown intact.

**Architecture:** `ActionMenu.tsx` dual-renders (Tailwind `md:hidden` / `hidden md:contents`, zero viewport JS) two list surfaces sharing ONE trigger and ONE gate state-machine: the existing `DropdownMenu` (desktop) and a new `Sheet side="bottom"` (mobile). Both shadcn primitives already exist (Radix Dialog based — no new dependency).

**Tech Stack:** React 19, shadcn `Sheet` (`packages-js/ui/src/shadcn/ui/sheet.tsx`), Radix Dialog, Tailwind v4, Vitest (JSDOM), Playwright E2E against the dogfood Docker stack (`:8090`).

---

## File Structure

- **Modify** `packages-js/ui/src/action/ActionMenu.tsx` — the only framework source change. Add the mobile `Sheet` subtree; wrap the desktop `DropdownMenu` in a `hidden md:contents` shell; share trigger + `handleSelect`/`confirm`/`form` state across both. Extract a small `renderTrigger()` helper to avoid duplicating the `⋯` button.
- **Modify** `packages-js/ui/tests/ActionMenu.test.tsx` — fix the JSDOM dual-render ambiguity (both surfaces co-exist with no CSS): scope ambiguous queries. Add a unit test asserting the Sheet surface exists with full-width items.
- **Create** `apps/showcase/tests/e2e/responsive-modals.spec.ts` — the E2E gate: bottom-sheet @360/640 (`widthRatio ≥ 0.9`, items ≥44px), Dropdown @768/1024/1440, no overflow.
- **Modify** `apps/showcase/app/Arqel/Resources/PostResource.php` — already has >3 row actions (verified: Edit/View/Export/Delete collapse the menu). No change expected; confirm in Task 1.

---

## Task 1: E2E gate — measure the mobile-sheet / desktop-dropdown contract (RED first)

**Files:**
- Create: `apps/showcase/tests/e2e/responsive-modals.spec.ts`
- Reference (read-only): `apps/showcase/tests/e2e/responsive.ts` (helpers: `atViewport`, `MOBILE_WIDTHS`, `assertNoHorizontalOverflow`), `apps/showcase/tests/e2e/fixtures.ts` (`loggedInPage`).

- [ ] **Step 1: Write the failing E2E spec**

```typescript
import { expect } from '@playwright/test';
import { test } from './fixtures';
import { assertNoHorizontalOverflow, atViewport } from './responsive';

/**
 * RESPONSIVE — modals/menus (Phase 3 gate).
 *
 * The collapsed row-actions menu must adopt the mobile idiom under 768px: a
 * full-width bottom Sheet with >=44px items, NOT the fixed 192px desktop
 * Dropdown popper. Desktop (>=768px) must keep the Dropdown untouched.
 *
 * Route: /admin/posts. PostResource has >3 row actions, so the menu collapses
 * behind a single "Actions" trigger (button, aria-label="Actions"). In mobile
 * the trigger lives in the DataTable card header (Phase 2); in desktop in the
 * table's actions cell. We always click the FIRST VISIBLE trigger.
 */

const MOBILE = [360, 640] as const;
const DESKTOP = [768, 1024, 1440] as const;

async function openActions(page: import('@playwright/test').Page) {
  const trigger = page.getByRole('button', { name: 'Actions' }).first();
  await trigger.click({ timeout: 5000 });
}

test.describe('responsive — modals', () => {
  test('mobile: a full-width bottom Sheet, no Dropdown popper', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    for (const w of MOBILE) {
      await atViewport(page, w, async () => {
        await openActions(page);

        const sheet = page.locator('[data-slot="sheet-content"]');
        await expect(sheet).toBeVisible();

        const box = await sheet.boundingBox();
        if (!box) throw new Error('sheet has no box');
        // full-width bottom-sheet: >=90% of the viewport, anchored to bottom.
        expect(box.width).toBeGreaterThanOrEqual(w * 0.9);
        expect(box.y + box.height).toBeGreaterThanOrEqual(900 - 2); // atViewport height=900

        // every action item is a >=44px touch target.
        const items = sheet.locator('[data-arqel-sheet-action]');
        const count = await items.count();
        expect(count).toBeGreaterThan(0);
        for (let i = 0; i < count; i++) {
          const ib = await items.nth(i).boundingBox();
          if (!ib) throw new Error('item has no box');
          expect(ib.height).toBeGreaterThanOrEqual(44);
        }

        // NO desktop dropdown menu open in mobile.
        await expect(page.locator('[role="menu"]')).toHaveCount(0);

        await assertNoHorizontalOverflow(page);
        await page.keyboard.press('Escape');
      });
    }
  });

  test('desktop: the Dropdown popper, no bottom Sheet', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    for (const w of DESKTOP) {
      await atViewport(page, w, async () => {
        await openActions(page);

        await expect(page.locator('[role="menu"]').first()).toBeVisible();
        // the mobile sheet must NOT be open on desktop.
        await expect(page.locator('[data-slot="sheet-content"]')).toHaveCount(0);

        await assertNoHorizontalOverflow(page);
        await page.keyboard.press('Escape');
      });
    }
  });
});
```

- [ ] **Step 2: Run it — verify it FAILS the right way**

Run (from `apps/showcase`, dogfood stack must be up on :8090):
```bash
APP_BASE_URL=http://localhost:8090 npx playwright test responsive-modals --reporter=line
```
Expected: the **mobile** test FAILS (no `[data-slot="sheet-content"]` — the Dropdown still renders at 360/640). The **desktop** test PASSES (Dropdown already works). This proves the gate measures the real gap.

- [ ] **Step 3: Commit the RED gate**

```bash
git add apps/showcase/tests/e2e/responsive-modals.spec.ts
git commit --no-verify --signoff -m "test(demo): action-menu bottom-sheet gate (mobile sheet / desktop dropdown)"
```
(Husky is broken on the host; `--no-verify` is the project convention after the gate is verified manually. The footer `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>` must be on the real commit — the controller adds it.)

---

## Task 2: Implement the mobile bottom-Sheet in ActionMenu (make Task 1 GREEN)

**Files:**
- Modify: `packages-js/ui/src/action/ActionMenu.tsx`
- Reference (read-only): `packages-js/ui/src/shadcn/ui/sheet.tsx` (exports `Sheet`, `SheetContent`, `SheetHeader`, `SheetTitle`, `SheetClose`), `packages-js/ui/src/action/Button.tsx` (`size="icon-touch"` = `size-11`).

**Context for the implementer:** `ActionMenu` collapses into a Radix `DropdownMenu` when `actions.length > inlineThreshold`. The collapsed branch (the `return (<> … </>)` at the end) is what changes. Today the dropdown items call `handleSelect` (a local state-machine that opens `ConfirmDialog` / `ActionFormModal` siblings, or calls `onInvoke`). We keep that machine and add a SECOND presentation surface — a bottom Sheet — sharing the SAME trigger, the SAME `handleSelect`, and the SAME gate siblings.

- [ ] **Step 1: Add the Sheet imports + a controlled-open state for the sheet**

At the top imports of `ActionMenu.tsx`, add:
```typescript
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from '../shadcn/ui/sheet.js';
```
Inside the component, alongside the existing `confirmAction`/`formAction` state, add:
```typescript
  // The mobile bottom-sheet is controlled so selecting an item can close it
  // (mirroring the Dropdown's onSelect auto-close).
  const [sheetOpen, setSheetOpen] = useState(false);
```

- [ ] **Step 2: Extract a shared trigger so it is not duplicated**

Just before the collapsed `return`, add:
```typescript
  // One visual trigger shared by both presentation surfaces. The Dropdown
  // wraps it via DropdownMenuTrigger (asChild); the Sheet opens it on click.
  const triggerNode = trigger ?? (
    <Button variant="ghost" size="icon-touch" aria-label="Actions">
      ⋯
    </Button>
  );
```

- [ ] **Step 3: Wrap the existing Dropdown in `hidden md:contents` and add the Sheet subtree**

Replace the collapsed `return (<> … </>)` block so it reads (keep `confirmAction`/`formAction` sibling blocks EXACTLY as they were, after the two surfaces):
```tsx
  return (
    <>
      {/* Desktop (>=md): the Radix dropdown popper, unchanged. */}
      <div className="hidden md:contents">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>{triggerNode}</DropdownMenuTrigger>
          <DropdownMenuContent align="end" sideOffset={6} className={cn('min-w-[12rem]', className)}>
            {actions.map((action) => (
              <DropdownMenuItem
                key={action.name}
                disabled={action.disabled === true}
                variant={action.color === 'destructive' ? 'destructive' : 'default'}
                onSelect={() => handleSelect(action)}
              >
                {action.label}
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      {/* Mobile (<md): a full-width bottom sheet with >=44px items. */}
      <div className="md:hidden">
        <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
          <button
            type="button"
            aria-label="Actions"
            className="inline-flex size-11 items-center justify-center rounded-md text-lg hover:bg-accent"
            onClick={() => setSheetOpen(true)}
          >
            ⋯
          </button>
          <SheetContent side="bottom" className="max-h-[80vh] gap-0 overflow-y-auto pb-[env(safe-area-inset-bottom)]">
            <SheetHeader className="px-4 pt-4 pb-2">
              <SheetTitle>Actions</SheetTitle>
            </SheetHeader>
            <div className="flex flex-col px-2 pb-2">
              {actions.map((action) => (
                <button
                  key={action.name}
                  type="button"
                  data-arqel-sheet-action=""
                  disabled={action.disabled === true}
                  className={cn(
                    'flex min-h-11 w-full items-center rounded-md px-3 text-left text-sm hover:bg-accent disabled:pointer-events-none disabled:opacity-50',
                    action.color === 'destructive' && 'text-destructive',
                  )}
                  onClick={() => {
                    setSheetOpen(false);
                    handleSelect(action);
                  }}
                >
                  {action.label}
                </button>
              ))}
            </div>
          </SheetContent>
        </Sheet>
      </div>

      {confirmAction && (
        <ConfirmDialog
          open
          onOpenChange={(next) => {
            if (!next) setConfirmAction(null);
          }}
          config={confirmAction.confirmation}
          onConfirm={handleConfirm}
          processing={processing}
        />
      )}
      {formAction && (
        <ActionFormModal
          open
          onOpenChange={(next) => {
            if (!next) setFormAction(null);
          }}
          action={formAction}
          fields={formFieldsByAction[formAction.name] ?? formAction.formFields ?? []}
          onSubmit={(values) => {
            const action = formAction;
            setFormAction(null);
            onInvoke(action, values);
          }}
          processing={processing}
        />
      )}
    </>
  );
```

Notes for the implementer:
- The desktop trigger uses `triggerNode` (the shared `<Button icon-touch>`); the mobile trigger is a bare `<button>` because `SheetTrigger asChild` is NOT used here (we open via controlled `sheetOpen` so the same `handleSelect` flow that closes-then-gates works cleanly). Both carry `aria-label="Actions"` so the E2E `getByRole('button', { name: 'Actions' })` finds the visible one per viewport.
- `md:contents` on the desktop wrapper keeps the Dropdown's portal/positioning unaffected (the wrapper box vanishes from layout at >=md while still toggling visibility at <md).
- Do NOT edit `sheet.tsx` or `dropdown-menu.tsx` (vendored primitives).

- [ ] **Step 4: Build the `ui` dist in a clean container (host tooling is broken)**

The host `node_modules` is root-owned; rollup native bindings fail on host. Build inside the running dogfood vite container:
```bash
docker compose -p arqel-dogfood -f apps/showcase/compose.dogfood.yml exec -T vite \
  sh -c "cd /app && pnpm --filter @arqel-dev/ui build"
```
Expected: `Build success`. Then invalidate the vite pre-bundle cache and restart so the showcase serves fresh dist:
```bash
docker compose -p arqel-dogfood -f apps/showcase/compose.dogfood.yml exec -T vite sh -c "rm -rf /app/apps/showcase/node_modules/.vite"
cd apps/showcase && docker compose -p arqel-dogfood -f compose.dogfood.yml restart vite
```
Wait until `curl -s -o /dev/null -w "%{http_code}" http://localhost:5180/@vite/client` returns `200`.

- [ ] **Step 5: Run the E2E gate — verify GREEN**

```bash
cd apps/showcase && APP_BASE_URL=http://localhost:8090 npx playwright test responsive-modals --reporter=line
```
Expected: BOTH tests PASS (mobile sheet full-width + ≥44px items; desktop dropdown intact; no overflow).

- [ ] **Step 6: Commit the implementation**

```bash
git add packages-js/ui/src/action/ActionMenu.tsx
git commit --no-verify --signoff -m "feat(ui): action menu becomes a bottom-sheet on mobile"
```

---

## Task 3: Fix the Vitest dual-render ambiguity + add a Sheet unit test

**Files:**
- Modify: `packages-js/ui/tests/ActionMenu.test.tsx`

**Context:** Under JSDOM there is NO CSS, so BOTH the desktop `div.hidden.md:contents` and the mobile `div.md:hidden` render their children. That means `getByRole('button', { name: 'Actions' })` now matches TWO triggers (dropdown + sheet) and throws "Found multiple elements". The existing tests that click the trigger then a `menuitem` must be scoped to the DESKTOP dropdown surface. Same trap we hit in Phase 2 (DataTable card-view).

- [ ] **Step 1: Scope the ambiguous trigger queries to the desktop dropdown**

The desktop dropdown is wrapped in `div.hidden.md:contents`. Add a stable hook so tests can scope to it WITHOUT relying on class names. In `ActionMenu.tsx` (Task 2's desktop wrapper), add `data-arqel-action-dropdown=""` to that wrapper div:
```tsx
      <div data-arqel-action-dropdown="" className="hidden md:contents">
```
Then in `ActionMenu.test.tsx`, add `within` to the testing-library import and scope each collapsed-branch query. Replace the three collapsed tests' interaction lines. Pattern (apply to the `#229`, `#231`, and plain-invoke tests, and the "collapses into a dropdown trigger" test):

```typescript
import { render, screen, within } from '@testing-library/react';
```

For "collapses into a dropdown trigger when count > threshold":
```typescript
    const dropdown = within(
      document.querySelector('[data-arqel-action-dropdown]') as HTMLElement,
    );
    expect(dropdown.getByRole('button', { name: 'Actions' })).toBeInTheDocument();
    expect(dropdown.queryByRole('button', { name: 'Edit' })).toBeNull();
```

For the three interaction tests (#229 / #231 / plain invoke), replace:
```typescript
    await user.click(screen.getByRole('button', { name: 'Actions' }));
    await user.click(screen.getByRole('menuitem', { name: 'Delete' }));
```
with:
```typescript
    const dropdown = within(
      document.querySelector('[data-arqel-action-dropdown]') as HTMLElement,
    );
    await user.click(dropdown.getByRole('button', { name: 'Actions' }));
    await user.click(dropdown.getByRole('menuitem', { name: 'Delete' }));
```
(Use the matching label per test: `Delete`, `Transfer`, `Ping`. The confirm/form modals are portaled to `document.body`, so `screen.findByText('Delete this row?')` / `'Reason'` and the final confirm `getByRole('button', { name: 'Delete' })` stay on `screen` — only the trigger + menuitem move to the scoped `dropdown`. The `menuitem` role lives only in the Radix dropdown, so it is unambiguous, but scoping the TRIGGER is mandatory.)

- [ ] **Step 2: Add a unit test for the mobile Sheet surface**

Append to the `describe('ActionMenu')` block:
```typescript
  it('renders a bottom-sheet surface with full-width >=44px action items', async () => {
    const user = userEvent.setup();
    const onInvoke = vi.fn();
    render(
      <ActionMenu
        inlineThreshold={3}
        actions={[
          makeAction('a', 'Edit'),
          makeAction('b', 'Restore'),
          makeAction('c', 'View'),
          makeAction('d', 'Ping'),
        ]}
        onInvoke={onInvoke}
      />,
    );

    // The mobile sheet trigger is the second "Actions" button (the sheet's
    // own bare <button>, distinct from the dropdown trigger). Open via it.
    const triggers = screen.getAllByRole('button', { name: 'Actions' });
    expect(triggers.length).toBeGreaterThanOrEqual(2); // dropdown + sheet co-exist under JSDOM
    await user.click(triggers[triggers.length - 1] as HTMLElement);

    // The sheet's action items carry the data hook and the min-h-11 class.
    const items = document.querySelectorAll('[data-arqel-sheet-action]');
    expect(items.length).toBe(4);
    items.forEach((el) => expect(el.className).toContain('min-h-11'));

    // Selecting a plain action invokes once.
    await user.click(
      document.querySelector('[data-arqel-sheet-action]:last-child') as HTMLElement,
    );
    expect(onInvoke).toHaveBeenCalledTimes(1);
    expect(onInvoke.mock.calls[0]?.[0]).toMatchObject({ name: 'd' });
  });
```

- [ ] **Step 3: Run the `ui` Vitest suite in a clean container — verify GREEN**

```bash
docker run --rm -v /home/diogo/PhpstormProjects/arqel:/app -w /app node:22-alpine \
  sh -c "corepack enable && CI=true pnpm install --frozen-lockfile && pnpm --filter @arqel-dev/ui test"
```
Expected: all tests PASS (the previously-ambiguous ones now scoped; the new sheet test green). If `pnpm install` is heavy, the existing warm `node_modules` in the vite container can be reused instead:
```bash
docker compose -p arqel-dogfood -f apps/showcase/compose.dogfood.yml exec -T vite \
  sh -c "cd /app && pnpm --filter @arqel-dev/ui test"
```

- [ ] **Step 4: Run biome + tsc on the `ui` package — verify clean**

```bash
docker compose -p arqel-dogfood -f apps/showcase/compose.dogfood.yml exec -T vite \
  sh -c "cd /app && pnpm --filter @arqel-dev/ui run typecheck && pnpm biome check packages-js/ui/src/action/ActionMenu.tsx"
```
Expected: no type errors, no biome diagnostics.

- [ ] **Step 5: Commit the test fixes**

```bash
git add packages-js/ui/tests/ActionMenu.test.tsx packages-js/ui/src/action/ActionMenu.tsx
git commit --no-verify --signoff -m "test(ui): scope dual-render queries + cover the bottom-sheet surface"
```
(Note: `ActionMenu.tsx` is re-added here only because Step 1 adds the `data-arqel-action-dropdown` hook to it.)

---

## Task 4: Regression sweep + PR

**Files:** none (verification only).

- [ ] **Step 1: Re-run the baseline defect-map — still 9/9**

```bash
cd apps/showcase && APP_BASE_URL=http://localhost:8090 npx playwright test --config=playwright.baseline.config.ts --reporter=line
```
Expected: 9 passed.

- [ ] **Step 2: Run the FULL default E2E suite (no regression on tables/shell/forms)**

```bash
cd apps/showcase && APP_BASE_URL=http://localhost:8090 npx playwright test --reporter=line
```
Expected: all green (includes the new `responsive-modals` + the Phase 1/2 specs).

- [ ] **Step 3: Push the branch and open the PR**

```bash
git push -u origin round-design/responsive-modals
```
Open the PR via the host's curl workaround (GitHub DNS is broken):
```bash
curl --resolve api.github.com:443:140.82.112.6 -H "Authorization: Bearer $(gh auth token)" \
  -H "Accept: application/vnd.github+json" \
  https://api.github.com/repos/arqel-dev/arqel/pulls \
  -d '{"title":"feat(ui): action menu bottom-sheet on mobile (responsive Phase 3)","head":"round-design/responsive-modals","base":"main","body":"..."}'
```
PR body summarizes: the 192px-fixed-popper gap, the dual-render Sheet fix, the gate (`responsive-modals.spec.ts`), and that baseline stays 9/9. End with the `🤖 Generated with [Claude Code]` footer.

- [ ] **Step 4: Wait for CI green, then squash-merge**

Poll the PR's check-runs via the same `--resolve` curl. Once all green, squash-merge, delete the branch, sync `main`. Report Phase 3 convergence. (The v0.x tag remains the maintainer's manual step — do NOT cut it.)

---

## Self-Review

**Spec coverage:**
- bottom-sheet under 768px → Task 2 (Sheet subtree) + Task 1 (gate `widthRatio ≥ 0.9`). ✅
- items ≥44px → Task 2 (`min-h-11`) + Task 1 (per-item boundingBox ≥44) + Task 3 (unit class assert). ✅
- desktop dropdown intact → Task 2 (`hidden md:contents` wrapper) + Task 1 (desktop test). ✅
- gate confirm/form from both modes → Task 2 (shared `handleSelect`/siblings) + Task 3 (#229/#231 still pass). ✅
- SheetTitle accessible → Task 2 (`<SheetTitle>Actions</SheetTitle>`). ✅
- baseline 9/9 → Task 4 Step 1. ✅
- Vitest green under dual-render → Task 3. ✅
- biome + tsc clean → Task 3 Step 4. ✅
- no new dependency → uses existing `sheet.tsx`. ✅

**Placeholder scan:** PR body `"..."` in Task 4 Step 3 is intentionally filled at execution (the controller writes the real body); every code/test step has complete content. No TBD/TODO in source or tests.

**Type consistency:** `handleSelect`, `confirmAction`, `formAction`, `setSheetOpen`, `triggerNode`, `data-arqel-sheet-action`, `data-arqel-action-dropdown` are used consistently across Tasks 1-3. The E2E selector `[data-slot="sheet-content"]` matches the shadcn `SheetContent` `data-slot`. The unit test's `[data-arqel-sheet-action]` matches Task 2's attribute.
