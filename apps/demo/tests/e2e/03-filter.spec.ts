import { expect, test } from './fixtures';

test.describe('Posts filter (SelectFilter)', () => {
  test('filtering status=published reduces the list', async ({ loggedInPage }) => {
    await loggedInPage.goto('/admin/posts');
    await expect(loggedInPage.locator('table tbody tr').first()).toBeVisible();

    const totalBefore = await loggedInPage.locator('table tbody tr').count();
    expect(totalBefore).toBe(25);

    const allSelects = loggedInPage.locator('select');
    const statusSelect = allSelects.filter({
      has: loggedInPage.locator('option[value="published"]'),
    });
    await statusSelect.selectOption('published');

    // URL encodes brackets as %5B / %5D.
    await loggedInPage.waitForURL(/filter(\[|%5B)status(\]|%5D)=published/);

    const totalAfter = await loggedInPage.locator('table tbody tr').count();
    expect(totalAfter).toBeLessThan(25);
    expect(totalAfter).toBeGreaterThan(0);
  });
});
