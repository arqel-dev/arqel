import { test } from './fixtures';
import { assertTouchTargets, atViewport } from './responsive';

/**
 * Touch-target regression guard (WCAG 2.5.5 / Apple HIG → 44px).
 *
 * The responsive baseline proved two shared UI controls rendered below the
 * 44px minimum on mobile: the FormTabs `[role="tab"]` trigger (~38px tall)
 * and the DataTable sort `<button>` inside a column header (~39×20px). This
 * spec asserts both now meet the 44px floor at the two mobile widths.
 */
test.describe('responsive — touch targets (≥44px)', () => {
  test('form tab triggers are ≥44px tall on mobile', async ({ loggedInPage: page }) => {
    await page.goto('/admin/posts/create'); // PostResource form uses Tabs
    for (const w of [360, 640] as const) {
      await atViewport(page, w, async () => {
        await assertTouchTargets(page, '[role="tab"]', 44);
      });
    }
  });

  test('table sort buttons are ≥44px on mobile', async ({ loggedInPage: page }) => {
    await page.goto('/admin/posts');
    for (const w of [360, 640] as const) {
      await atViewport(page, w, async () => {
        await assertTouchTargets(page, 'thead th button', 44);
      });
    }
  });
});
