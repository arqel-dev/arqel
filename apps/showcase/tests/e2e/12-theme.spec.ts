import { expect, test } from './fixtures';

/**
 * The shell UserMenu (@arqel-dev/ui shell) owns the theme control: opening the
 * user menu reveals a horizontal segmented control with "Light" / "Dark" /
 * "System" icon buttons (each an `aria-pressed` toggle). Picking "Dark" /
 * "Light" is what drives the `dark` class on the <html> element (the
 * standalone @arqel-dev/theme ThemeToggle does not).
 */
test.describe('Theme control', () => {
  test('the user-menu theme segmented control flips the dark class on <html>', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    const html = page.locator('html');
    const openMenu = () => page.getByRole('button', { name: /open user menu/i }).click();

    // The segmented control lives inside the dropdown; open it, then pick Dark.
    await openMenu();
    const dark = page.getByRole('button', { name: /^dark$/i });
    await expect(dark).toBeVisible();
    await dark.click();
    await expect(html).toHaveClass(/\bdark\b/);

    // Reopen the menu before switching back — the segment buttons are plain
    // <button>s so the dropdown is expected to stay open, but reopening keeps
    // the test resilient regardless of dismiss behavior.
    if (!(await page.getByRole('button', { name: /^light$/i }).isVisible())) {
      await openMenu();
    }
    await page.getByRole('button', { name: /^light$/i }).click();
    await expect(html).not.toHaveClass(/\bdark\b/);
  });
});
