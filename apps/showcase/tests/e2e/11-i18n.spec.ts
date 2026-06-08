import { expect, test } from './fixtures';

/**
 * The @arqel-dev/i18n LocaleSwitcher is mounted in the shell topbar (the
 * userMenu slot). It renders a Radix Select (role="combobox") wrapped in a
 * `[data-arqel-locale-switcher]` container, showing the active locale's
 * display label (e.g. "English").
 */
test.describe('i18n locale switcher', () => {
  test('the locale switcher is present in the topbar on the posts page', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    const switcher = page.locator('[data-arqel-locale-switcher]');
    await expect(switcher).toBeVisible();

    // Its trigger is a Radix Select combobox displaying the active locale.
    const trigger = switcher.getByRole('combobox');
    await expect(trigger).toBeVisible();
    await expect(trigger).toContainText('English');
  });
});
