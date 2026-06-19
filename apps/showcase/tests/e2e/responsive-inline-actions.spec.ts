import { expect, type Page } from '@playwright/test';
import { test } from './fixtures';
import { assertNoHorizontalOverflow, atViewport, type ViewportWidth } from './responsive';

/**
 * RESPONSIVE — inline row-action buttons (Phase 6 gate).
 *
 * When a resource has few enough row actions (<= inlineThreshold), <ActionMenu>
 * renders them inline as a row of buttons via <ActionButton size="sm">. That
 * `size="sm"` is 32px tall — below the 44px touch floor (WCAG 2.5.5) on mobile.
 * They must grow to >=44px under 768px and stay dense (<=33px) on desktop
 * (no-regression), with no horizontal overflow at any tier.
 *
 * Route: /admin/authors (AuthorResource keeps Edit + Delete inline, so the
 * buttons render directly in each row's actions cell — no dropdown collapse).
 */

const MOBILE: ViewportWidth[] = [360, 640];
const DESKTOP: ViewportWidth[] = [768, 1024, 1440];

// Inline action buttons live in table body cells (desktop) or, below 768px,
// in the DataTable card surface (Phase 2). Cover both and measure only the
// visible ones.
const INLINE_ACTIONS = 'main td button, main [data-arqel-data-card] button';

/** Settled heights of the visible Edit/Delete inline action buttons. */
async function actionHeights(page: Page): Promise<number[]> {
  return page.evaluate(async (sel) => {
    const frame = () => new Promise((r) => requestAnimationFrame(() => r(null)));
    const match = (el: Element) => /edit|delete|restore|view/i.test((el.textContent ?? '').trim());
    const els = (Array.from(document.querySelectorAll(sel)) as HTMLElement[])
      .filter(match)
      .filter((el) => {
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0; // visible only (dual-render safe)
      });
    const heights: number[] = [];
    for (const el of els) {
      let prev = -1;
      let h = el.getBoundingClientRect().height;
      for (let i = 0; i < 10 && Math.abs(h - prev) >= 0.5; i++) {
        prev = h;
        // eslint-disable-next-line no-await-in-loop
        await frame();
        h = el.getBoundingClientRect().height;
      }
      heights.push(h);
    }
    return heights;
  }, INLINE_ACTIONS);
}

test.describe('responsive — inline actions', () => {
  test('mobile: inline Edit/Delete are >=44px touch targets', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/authors');
    await page.waitForLoadState('networkidle');

    for (const w of MOBILE) {
      await atViewport(page, w, async () => {
        const heights = await actionHeights(page);
        expect(heights.length, `inline actions present @${w}`).toBeGreaterThan(0);
        for (const [i, h] of heights.entries()) {
          // 44px floor with sub-pixel tolerance (border-box + device rounding).
          expect(h, `inline action[${i}] height ${h} < 44 @${w}`).toBeGreaterThanOrEqual(43.5);
        }
        await assertNoHorizontalOverflow(page);
      });
    }
  });

  test('desktop: inline actions stay dense (<=33px, no-regression)', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/authors');
    await page.waitForLoadState('networkidle');

    for (const w of DESKTOP) {
      await atViewport(page, w, async () => {
        const heights = await actionHeights(page);
        expect(heights.length, `inline actions present @${w}`).toBeGreaterThan(0);
        for (const [i, h] of heights.entries()) {
          // dense 32px control on desktop (allow a px of slack).
          expect(h, `inline action[${i}] regressed to ${h} (>33) @${w}`).toBeLessThanOrEqual(33);
        }
        await assertNoHorizontalOverflow(page);
      });
    }
  });
});
