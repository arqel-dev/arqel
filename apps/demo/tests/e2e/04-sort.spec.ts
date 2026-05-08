import { expect, test } from './fixtures';

test.describe('Posts sort by column', () => {
  test('clicking the title header toggles sort asc/desc', async ({ loggedInPage }) => {
    await loggedInPage.goto('/admin/posts');
    await expect(loggedInPage.locator('table tbody tr').first()).toBeVisible();

    // Sort trigger is a <button> inside the columnheader <th>.
    const titleHeaderButton = loggedInPage
      .getByRole('columnheader', { name: /title/i })
      .getByRole('button', { name: /title/i });
    await titleHeaderButton.click();
    await loggedInPage.waitForURL(/sort=title/);
    expect(loggedInPage.url()).toMatch(/direction=asc/);

    await titleHeaderButton.click();
    await loggedInPage.waitForURL(/direction=desc/);
    expect(loggedInPage.url()).toMatch(/sort=title/);
    expect(loggedInPage.url()).toMatch(/direction=desc/);
  });
});
