import { expect, test } from './fixtures';

/**
 * PostResource exposes a CSV ExportAction as a bulk action. Selecting rows
 * reveals the bulk-action bar; clicking Export runs the export and flashes a
 * download_url, which the success FlashToast renders as a "Baixar" link
 * (data-testid=flash-download-link). Clicking it downloads the CSV.
 */
test.describe('Export bulk action', () => {
  test('exports selected rows and offers a downloadable CSV via the flash link', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    // Enter bulk mode (select all) and trigger Export.
    await page.locator('thead input[type="checkbox"], thead [role="checkbox"]').first().click();
    await page.getByRole('button', { name: /export/i }).click();

    // The success toast surfaces the download link.
    const link = page.getByTestId('flash-download-link');
    await expect(link).toBeVisible();

    // Clicking it downloads a non-empty CSV.
    const [download] = await Promise.all([page.waitForEvent('download'), link.click()]);
    const path = await download.path();
    expect(path).toBeTruthy();
    expect(download.suggestedFilename()).toMatch(/\.csv$/i);
  });
});
