import { expect, test } from './fixtures';

/**
 * MediaResource exposes an image upload field (Fields\Types\ImageField on
 * `file_path`). The create page must render a real <input type="file"> so
 * the upload control is functional, not a stub.
 *
 * Real-DOM notes: the create heading is "Create Media"; the form mounts a
 * `title` text field and a `file_path` image field whose control is a
 * single <input type="file">.
 */
test.describe('Upload field (Media)', () => {
  test('the media create page renders an image file input', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/media-assets/create');
    await expect(page.getByRole('heading', { name: 'Create Media' })).toBeVisible();

    const fileInput = page.locator('input[type="file"]');
    await expect(fileInput).toHaveCount(1);
    await expect(fileInput).toBeAttached();
    // It accepts images.
    await expect(page.locator('[data-arqel-field="file_path"]')).toBeVisible();
  });
});
