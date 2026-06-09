import { expect, test } from './fixtures';

/**
 * The shell Topbar (@arqel-dev/ui shell) ships its own theme toggle button
 * with aria-label "Switch to dark theme" / "Switch to light theme" and a
 * ☾/☀ glyph. This toggle is the one that drives the `dark` class on the
 * <html> element (the standalone @arqel-dev/theme ThemeToggle does not).
 */
test.describe('Theme toggle', () => {
  test('the topbar theme toggle flips the dark class on <html>', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    const html = page.locator('html');
    const toggle = page.getByRole('button', { name: /switch to (dark|light) theme/i });
    await expect(toggle).toBeVisible();

    const before = (await html.getAttribute('class')) ?? '';
    const wasDark = /\bdark\b/.test(before);

    await toggle.click();

    if (wasDark) {
      await expect(html).not.toHaveClass(/\bdark\b/);
    } else {
      await expect(html).toHaveClass(/\bdark\b/);
    }

    // Toggling back restores the original mode.
    await page.getByRole('button', { name: /switch to (dark|light) theme/i }).click();
    if (wasDark) {
      await expect(html).toHaveClass(/\bdark\b/);
    } else {
      await expect(html).not.toHaveClass(/\bdark\b/);
    }
  });
});
