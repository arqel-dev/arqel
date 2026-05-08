import { expect, test } from './fixtures';

test.describe('Dashboard', () => {
  test('renders MainDashboard with 3 widgets', async ({ loggedInPage }) => {
    await loggedInPage.goto('/admin/dashboards/main');

    // Heading + chrome from Dashboard::heading('Overview') / description.
    await expect(loggedInPage.getByRole('heading', { name: /overview/i })).toBeVisible();

    // Stat widget heading.
    await expect(loggedInPage.getByText(/total posts/i)).toBeVisible();

    // Chart widget heading + a Recharts <svg> rendered into the page.
    await expect(loggedInPage.getByText(/posts per day/i)).toBeVisible();
    await expect(loggedInPage.locator('svg').first()).toBeVisible();

    // Table widget heading + at least one row of data (PostSeeder).
    await expect(loggedInPage.getByText(/recent posts/i)).toBeVisible();
    const tableRows = loggedInPage.locator('table tbody tr');
    expect(await tableRows.count()).toBeGreaterThan(0);
  });
});
