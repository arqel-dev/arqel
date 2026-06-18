import { expect, type Page } from '@playwright/test';
import { test } from './fixtures';
import { assertNoHorizontalOverflow, atViewport, type ViewportWidth } from './responsive';

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
 * table actions cell. We always click the FIRST VISIBLE trigger.
 */

const MOBILE: ViewportWidth[] = [360, 640];
const DESKTOP: ViewportWidth[] = [768, 1024, 1440];

async function openActions(page: Page) {
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
