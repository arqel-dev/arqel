import { expect, type Page } from '@playwright/test';
import { test } from './fixtures';
import { assertNoHorizontalOverflow, atViewport, type ViewportWidth } from './responsive';

/**
 * RESPONSIVE — table toolbar controls (Phase 5 gate).
 *
 * The shared table toolbar renders a search <input> (ResourceIndex) and one
 * control per filter (<TableFilters>: select / text / ternary). Before this
 * phase they were 36px tall (h-9) — below the 44px touch floor (WCAG 2.5.5) on
 * mobile. They must grow to >=44px under 768px and stay compact (<=37px) on
 * desktop (no-regression), with no horizontal overflow at any tier.
 *
 * Route: /admin/posts (PostResource declares a search box + select/ternary
 * filters, so every toolbar control type is on screen).
 *
 * Scoped to <main> so the deliberately-dense shell topbar controls are excluded.
 */

const MOBILE: ViewportWidth[] = [360, 640];
const DESKTOP: ViewportWidth[] = [768, 1024, 1440];

const SEARCH = 'main input[type="search"]';
// filter controls live in the <fieldset> labelled "Filters" (sr-only legend).
const FILTER_SELECTS = 'main fieldset select';
const FILTER_TEXTS = 'main fieldset input[type="text"]';

/**
 * The toolbar is `flex-wrap`; a mobile width can reflow it, and the controls
 * settle a frame or two after the viewport change. Read each control's settled
 * height (poll until two consecutive rAF samples agree).
 */
async function settledHeights(page: Page, selector: string): Promise<number[]> {
  return page.evaluate(async (sel) => {
    const els = Array.from(document.querySelectorAll(sel)) as HTMLElement[];
    const frame = () => new Promise((r) => requestAnimationFrame(() => r(null)));
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
  }, selector);
}

test.describe('responsive — table controls', () => {
  test('mobile: search + filter controls are >=44px touch targets', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    for (const w of MOBILE) {
      await atViewport(page, w, async () => {
        for (const [name, sel] of [
          ['search', SEARCH],
          ['filter-select', FILTER_SELECTS],
          ['filter-text', FILTER_TEXTS],
        ] as const) {
          const heights = await settledHeights(page, sel);
          // text filters may be absent on this route — only assert what renders.
          for (const [i, h] of heights.entries()) {
            // 44px floor with a sub-pixel tolerance (border-box + device rounding).
            expect(h, `${name}[${i}] height ${h} < 44 @${w}`).toBeGreaterThanOrEqual(43.5);
          }
        }
        // at least the search box and one filter select must exist on /posts.
        expect((await settledHeights(page, SEARCH)).length, 'search present').toBeGreaterThan(0);
        expect(
          (await settledHeights(page, FILTER_SELECTS)).length,
          'a filter select present',
        ).toBeGreaterThan(0);

        await assertNoHorizontalOverflow(page);
      });
    }
  });

  test('desktop: the controls stay compact (<=37px, no-regression)', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    for (const w of DESKTOP) {
      await atViewport(page, w, async () => {
        for (const [name, sel] of [
          ['search', SEARCH],
          ['filter-select', FILTER_SELECTS],
        ] as const) {
          const heights = await settledHeights(page, sel);
          for (const [i, h] of heights.entries()) {
            // dense 36px control on desktop (allow a couple px of slack).
            expect(h, `${name}[${i}] regressed to ${h} (>37) @${w}`).toBeLessThanOrEqual(37);
          }
        }
        await assertNoHorizontalOverflow(page);
      });
    }
  });
});
