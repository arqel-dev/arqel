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
});
