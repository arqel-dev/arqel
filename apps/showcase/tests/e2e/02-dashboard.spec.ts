import { expect, test } from './fixtures';

/**
 * The `/admin` landing dashboard (MainDashboard) renders the full widget
 * stack from `arqel-dev/widgets`: three StatWidgets (Posts, Authors, Open
 * Tickets), a ChartWidget (posts by status) and a TableWidget (recent
 * posts). The widget grid carries `data-testid="dashboard-grid"`, each
 * StatWidget is a `<section aria-label="…">` with an `<h2>` heading and a
 * numeric value.
 */
test.describe('Dashboard', () => {
  test('renders the overview heading + stat widgets + chart + recent posts', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;

    await page.goto('/admin');

    // Page heading.
    await expect(page.getByRole('heading', { name: 'Overview', level: 1 })).toBeVisible();

    // Widget grid.
    const grid = page.locator('[data-testid="dashboard-grid"]');
    await expect(grid).toBeVisible();

    // Stat widgets render with their headings (exact, to disambiguate the
    // 'Posts' stat from the 'Posts by status' / 'Recent posts' widgets).
    await expect(page.getByRole('heading', { name: 'Posts', exact: true, level: 2 })).toBeVisible();
    await expect(
      page.getByRole('heading', { name: 'Authors', exact: true, level: 2 }),
    ).toBeVisible();
    await expect(
      page.getByRole('heading', { name: 'Open Tickets', exact: true, level: 2 }),
    ).toBeVisible();

    // The Posts stat links to the posts list and shows a numeric value.
    const statLink = page.locator('[data-testid="stat-card-link"]').first();
    await expect(statLink).toBeVisible();
    await expect(statLink).toHaveAttribute('href', '/admin/posts');

    // Chart widget container.
    await expect(page.getByRole('heading', { name: 'Posts by status', level: 2 })).toBeVisible();

    // Recent-posts table widget.
    await expect(page.getByRole('heading', { name: 'Recent posts', level: 2 })).toBeVisible();
  });

  // Regression guard: the dashboard route must carry the shared
  // HandleArqelInertiaRequests middleware (like resource routes), so the admin
  // shell receives `panel.navigation` (the sidebar menu) and the full `i18n`
  // payload (the locale switcher's available locales). Before the fix the
  // dashboard rendered with an empty sidebar and a single-locale switcher.
  test('renders the shared shell: sidebar menu + full locale switcher (parity with resources)', async ({
    loggedInPage: page,
  }) => {
    const navCount = async () => page.locator('nav a, aside a, [data-slot="sidebar"] a').count();
    const localeOptions = async () => {
      const trigger = page
        .locator(
          '[data-arqel-locale-switcher] [role="combobox"], [data-arqel-locale-switcher] button',
        )
        .first();
      await trigger.click();
      await page.waitForTimeout(250);
      const opts = await page.locator('[role="option"]').allTextContents();
      await page.keyboard.press('Escape');
      return opts;
    };

    // Resource page is the reference shell.
    await page.goto('/admin/posts', { waitUntil: 'networkidle' });
    const postsNav = await navCount();
    const postsLocales = await localeOptions();
    expect(postsNav, 'resource page must show a populated sidebar').toBeGreaterThan(0);
    expect(postsLocales.length, 'resource page must list >1 locale').toBeGreaterThan(1);

    // Dashboard must match the reference shell.
    await page.goto('/admin', { waitUntil: 'networkidle' });
    expect(await navCount(), 'dashboard sidebar must match the resource shell').toBe(postsNav);
    expect(
      await localeOptions(),
      'dashboard locale switcher must list the same locales as resources',
    ).toEqual(postsLocales);
  });
});
