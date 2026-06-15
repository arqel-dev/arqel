import { expect } from '@playwright/test';
import { test } from './fixtures';
import {
  assertNoHorizontalOverflow,
  assertTouchTargets,
  atViewport,
  forEachViewport,
  shot,
} from './responsive';

/**
 * Phase-1 responsive gate for the Forms surface.
 *
 * The shared <FormGrid> accepts a responsive column map ({ sm, md, lg }) but
 * must actually reflow: 1 column on mobile (360 < sm) → multi-column on desktop
 * (1440 ≥ lg). The GridFormDemo route renders a tabs-free { sm:1, md:2, lg:3 }
 * grid so the reflow can be measured in isolation. We also guard the FormTabs
 * surface (PostResource create form) for touch + no-overflow on mobile.
 */
test.describe('responsive — forms', () => {
  test('the form grid reflows to 1 column on mobile and multi-column on desktop', async ({
    loggedInPage: page,
  }) => {
    await page.goto('/admin/grid-form-demo');

    // The demo nests the FormGrid inside FormRenderer's own `grid gap-4`
    // wrapper, so there are two `.grid` elements. The FormGrid is the one that
    // carries the field cells (6 children) — select it by that, not by `.grid`
    // order, so we measure the column map under test and not the outer wrapper.
    const trackCount = () =>
      page.evaluate(() => {
        const grids = Array.from(
          document.querySelectorAll('[data-testid="grid-form-demo"] .grid'),
        ) as HTMLElement[];
        const formGrid = grids.find((g) => g.children.length === 6);
        if (!formGrid) throw new Error('FormGrid container (6 cells) not found');
        const cols = getComputedStyle(formGrid).gridTemplateColumns;
        return cols.trim().split(/\s+/).filter(Boolean).length;
      });

    await atViewport(page, 360, async () => {
      await shot(page, 'forms-grid', 360);
      expect(await trackCount()).toBe(1);
    });
    await atViewport(page, 768, async () => {
      expect(await trackCount()).toBe(2); // md:2
    });
    await atViewport(page, 1440, async () => {
      await shot(page, 'forms-grid', 1440);
      expect(await trackCount()).toBe(3); // lg:3
    });
  });

  test('the grid form has no horizontal overflow at any viewport', async ({
    loggedInPage: page,
  }) => {
    await page.goto('/admin/grid-form-demo');
    await forEachViewport(page, async () => {
      await assertNoHorizontalOverflow(page);
    });
  });

  test('the form tab bar stays touchable and within bounds on mobile', async ({
    loggedInPage: page,
  }) => {
    await page.goto('/admin/posts/create'); // PostResource form uses FormTabs
    for (const w of [360, 640] as const) {
      await atViewport(page, w, async () => {
        await assertTouchTargets(page, '[role="tab"]', 44);
        await assertNoHorizontalOverflow(page);
      });
    }
  });
});
