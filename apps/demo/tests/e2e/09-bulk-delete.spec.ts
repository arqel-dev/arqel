import { expect, test } from './fixtures';

test.describe('BUG-VAL-003: bulk delete', () => {
  test('select 3 rows, click Delete selected, records removed', async ({ loggedInPage }) => {
    await loggedInPage.goto('/admin/posts');
    await expect(loggedInPage.locator('table tbody tr').first()).toBeVisible();

    const totalBefore = await loggedInPage.locator('table tbody tr').count();
    expect(totalBefore).toBeGreaterThanOrEqual(3);

    const checkboxes = loggedInPage.locator('table tbody input[type="checkbox"]');
    await checkboxes.nth(0).check();
    await checkboxes.nth(1).check();
    await checkboxes.nth(2).check();

    const bulkButton = loggedInPage.getByRole('button', { name: /delete selected/i });
    await expect(bulkButton).toBeVisible();
    await bulkButton.click();

    const modal = loggedInPage.getByRole('dialog');
    if (await modal.isVisible({ timeout: 1_000 }).catch(() => false)) {
      const confirmButton = modal.getByRole('button', { name: /delete|confirm/i }).last();
      await confirmButton.click();
    }

    await loggedInPage.waitForLoadState('networkidle');

    const totalAfter = await loggedInPage.locator('table tbody tr').count();
    expect(totalAfter).toBe(totalBefore - 3);
  });
});
