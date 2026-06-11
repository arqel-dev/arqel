import { test } from './fixtures';
import {
  assertNoHorizontalOverflow,
  assertTouchTargets,
  atViewport,
  forEachViewport,
  MOBILE_WIDTHS,
  shot,
} from './responsive';

/**
 * RESPONSIVE BASELINE — the defect map for the responsive-design loop.
 *
 * This spec measures the 6 target surfaces' CURRENT responsive state across the
 * 5 loop viewports (360 / 640 / 768 / 1024 / 1440). It is deliberately the
 * "before" snapshot: it is EXPECTED to be RED on the surfaces that still have
 * responsiveness gaps (e.g. forms overflowing at 360px). That red IS the
 * deliverable — it records exactly which surface × width breaks and on which
 * metric, and it drops a full-page screenshot per surface × width into
 * `test-results/responsive/` for human before/after review.
 *
 * It is therefore EXCLUDED from the green CI gate: the describe title carries
 * the `[@baseline]` tag so the CI Playwright run can grep it out with
 * `playwright test --grep-invert @baseline` (wired in a later task). Run it on
 * demand with `playwright test --grep @baseline` to refresh the defect map.
 *
 * Surface → route map (verified against the dogfood stack at :8090):
 *  - forms-grid  → /admin/grid-form-demo   (the tabs-free Grid form)
 *  - forms-tabs  → /admin/posts/create     (PostResource form, uses Radix Tabs)
 *  - tables      → /admin/posts            (the resource index table)
 *  - shell       → /admin/posts            (topbar/sidebar chrome)
 *  - modals      → /admin/posts → open the row "Actions" dropdown menu
 *  - dashboard   → /admin                  (MainDashboard widget grid)
 *  - typography  → /admin/posts            (page headings)
 *
 * Primary metric per surface: `assertNoHorizontalOverflow(page)` on the document
 * (scrollWidth <= clientWidth + 1px). It is the highest-signal, lowest-false-
 * positive responsiveness check. Touch-target checks live in their OWN tests at
 * the end (so a small tab/button never masks an overflow gap) and are applied
 * sparingly, only to real interactive elements ([role=tab], the table's
 * <button>s), at the mobile widths — never an inner svg/span child.
 */
test.describe('responsive baseline [@baseline]', () => {
  test('forms-grid — the tabs-free Grid form across viewports', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/grid-form-demo');
    await forEachViewport(page, async (w) => {
      await shot(page, 'forms-grid', w);
      await assertNoHorizontalOverflow(page);
    });
  });

  test('forms-tabs — the PostResource create form (Tabs) across viewports', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts/create');
    await forEachViewport(page, async (w) => {
      await shot(page, 'forms-tabs', w);
      await assertNoHorizontalOverflow(page);
    });
  });

  test('tables — the posts index table across viewports', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');
    await forEachViewport(page, async (w) => {
      await shot(page, 'tables', w);
      await assertNoHorizontalOverflow(page);
    });
  });

  test('shell — the admin chrome (topbar/sidebar) across viewports', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');
    await forEachViewport(page, async (w) => {
      await shot(page, 'shell', w);
      await assertNoHorizontalOverflow(page);
    });
  });

  test('modals — the open row Actions dropdown across viewports', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');
    // Open the per-row Actions dropdown FIRST so the menu is measured open.
    // PostResource's row has >3 actions, so they collapse into a dropdown whose
    // trigger is a button with aria-label="Actions" (verified in 05-actions).
    await page.locator('table tbody tr').first().getByRole('button', { name: 'Actions' }).click();
    await forEachViewport(page, async (w) => {
      await shot(page, 'modals', w);
      await assertNoHorizontalOverflow(page);
    });
  });

  test('dashboard — the MainDashboard widget grid across viewports', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin');
    await forEachViewport(page, async (w) => {
      await shot(page, 'dashboard', w);
      await assertNoHorizontalOverflow(page);
    });
  });

  test('typography — the posts page headings across viewports', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');
    await forEachViewport(page, async (w) => {
      await shot(page, 'typography', w);
      await assertNoHorizontalOverflow(page);
    });
  });

  // Touch-target checks live in their own tests (the document-overflow check is
  // the primary metric and must not be masked by a touch-target failure). They
  // run only at the mobile widths and scope to the REAL interactive element
  // ([role=tab], the table's <button>s) — never an inner svg/span child.
  test('touch-targets — forms-tabs tab triggers at mobile widths', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts/create');
    for (const w of MOBILE_WIDTHS) {
      await atViewport(page, w, () => assertTouchTargets(page, '[role="tab"]', 44));
    }
  });

  test('touch-targets — tables action buttons at mobile widths', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');
    for (const w of MOBILE_WIDTHS) {
      await atViewport(page, w, () => assertTouchTargets(page, 'table button', 44));
    }
  });
});
