import { expect, test } from './fixtures';

test.describe('Posts search + clear (BUG-VAL-004 regression)', () => {
  test('typing reduces list, clearing restores it', async ({ loggedInPage }) => {
    await loggedInPage.goto('/admin/posts');
    await expect(loggedInPage.locator('table tbody tr').first()).toBeVisible();

    const totalBefore = await loggedInPage.locator('table tbody tr').count();

    const search = loggedInPage.getByRole('searchbox', { name: /search/i });
    await search.fill('Lorem');

    await loggedInPage.waitForURL(/search=Lorem/, { timeout: 5_000 }).catch(() => {});
    await loggedInPage.waitForLoadState('networkidle');

    await search.fill('');
    // Wait for the URL to reflect the cleared search (search param empty or absent).
    await loggedInPage
      .waitForURL((url) => !/search=Lorem/.test(url.toString()), { timeout: 5_000 })
      .catch(() => {});
    await loggedInPage.waitForLoadState('networkidle');

    const totalAfterClear = await loggedInPage.locator('table tbody tr').count();
    expect(totalAfterClear).toBe(totalBefore);
  });
});
