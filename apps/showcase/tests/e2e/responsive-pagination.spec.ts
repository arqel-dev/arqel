import { expect, type Page } from '@playwright/test';
import { test } from './fixtures';
import { assertNoHorizontalOverflow, atViewport, type ViewportWidth } from './responsive';

/**
 * RESPONSIVE — table pagination (Phase 4 gate).
 *
 * The shared <TablePagination> (@arqel-dev/ui) renders Prev/Next buttons and a
 * per-page <select> that, before this phase, were 32px tall (Button size="sm" /
 * select h-8) — below the 44px touch floor on mobile. They must grow to >=44px
 * under 768px and stay compact (<=36px) on desktop (no-regression), with no
 * horizontal overflow at any tier.
 *
 * Route: /admin/posts (PostResource is seeded past one page, so the Pagination
 * nav renders with an enabled Next + the per-page picker).
 */

const MOBILE: ViewportWidth[] = [360, 640];
const DESKTOP: ViewportWidth[] = [768, 1024, 1440];

const PREV = 'nav[aria-label="Pagination"] button[aria-label="Previous page"]';
const NEXT = 'nav[aria-label="Pagination"] button[aria-label="Next page"]';
const PERPAGE = 'nav[aria-label="Pagination"] select';

/**
 * The Pagination <nav> is `flex-wrap`; entering a mobile width reflows it into
 * two rows, and the flex items settle to their final height a frame or two
 * after the viewport change. Read the element's settled height: poll until two
 * consecutive rAF samples agree (or a short budget elapses), so we measure the
 * layout the user actually sees rather than a mid-reflow frame.
 */
async function heightOf(page: Page, selector: string): Promise<number | null> {
  return page.evaluate(async (sel) => {
    const el = document.querySelector(sel) as HTMLElement | null;
    if (!el) return null;
    const frame = () => new Promise((r) => requestAnimationFrame(() => r(null)));
    let prev = -1;
    for (let i = 0; i < 10; i++) {
      const h = el.getBoundingClientRect().height;
      if (Math.abs(h - prev) < 0.5) return h;
      prev = h;
      await frame();
    }
    return prev;
  }, selector);
}

test.describe('responsive — pagination', () => {
  test('mobile: Prev/Next/per-page are >=44px touch targets', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    for (const w of MOBILE) {
      await atViewport(page, w, async () => {
        await expect(page.locator('nav[aria-label="Pagination"]')).toBeVisible();

        for (const [name, sel] of [
          ['Prev', PREV],
          ['Next', NEXT],
          ['per-page', PERPAGE],
        ] as const) {
          const h = await heightOf(page, sel);
          expect(h, `${name} not found @${w}`).not.toBeNull();
          // 44px floor with a sub-pixel tolerance: border-box + device rounding
          // lands a true 44px control at ~43.9px in getBoundingClientRect.
          expect(h ?? 0, `${name} height ${h} < 44 @${w}`).toBeGreaterThanOrEqual(43.5);
        }

        await assertNoHorizontalOverflow(page);
      });
    }
  });

  test('desktop: the controls stay compact (<=36px, no-regression)', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    for (const w of DESKTOP) {
      await atViewport(page, w, async () => {
        await expect(page.locator('nav[aria-label="Pagination"]')).toBeVisible();

        for (const [name, sel] of [
          ['Prev', PREV],
          ['Next', NEXT],
          ['per-page', PERPAGE],
        ] as const) {
          const h = await heightOf(page, sel);
          expect(h, `${name} not found @${w}`).not.toBeNull();
          // desktop keeps the dense 32px control (allow a couple px of slack).
          expect(h ?? 99, `${name} regressed to ${h} (>36) @${w}`).toBeLessThanOrEqual(36);
        }

        await assertNoHorizontalOverflow(page);
      });
    }
  });
});
