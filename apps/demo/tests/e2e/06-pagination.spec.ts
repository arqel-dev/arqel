import { expect, test } from './fixtures';

test.describe('BUG-VAL-001: pagination preserves perPage', () => {
  test('changing perPage to 10 then clicking Next shows different records', async ({
    loggedInPage,
  }) => {
    await loggedInPage.goto('/admin/posts');
    await expect(loggedInPage.locator('table tbody tr').first()).toBeVisible();

    const perPageSelect = loggedInPage
      .locator('select')
      .filter({
        has: loggedInPage.locator('option[value="10"]'),
      })
      .first();
    await perPageSelect.selectOption('10');
    await loggedInPage.waitForURL(/per_page=10/);

    expect(await loggedInPage.locator('table tbody tr').count()).toBe(10);
    const firstTitleBefore = await loggedInPage.locator('table tbody tr').first().textContent();

    await loggedInPage.getByRole('button', { name: /next page/i }).click();
    await loggedInPage.waitForURL(/page=2/);

    expect(loggedInPage.url()).toMatch(/per_page=10/);

    const firstTitleAfter = await loggedInPage.locator('table tbody tr').first().textContent();
    expect(firstTitleAfter).not.toBe(firstTitleBefore);
    expect(await loggedInPage.locator('table tbody tr').count()).toBe(10);
  });
});
