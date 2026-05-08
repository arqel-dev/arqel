import { expect, test } from './fixtures';

test.describe('Posts list', () => {
  test('renders 25 seeded posts paginated', async ({ loggedInPage }) => {
    await loggedInPage.goto('/admin/posts');
    await expect(loggedInPage.locator('table tbody tr').first()).toBeVisible();

    const rowCount = await loggedInPage.locator('table tbody tr').count();
    expect(rowCount).toBe(25);
  });
});
