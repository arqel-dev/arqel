import { expect, test } from './fixtures';

test.describe('BUG-VAL-002 + 011 + 012: row Edit full lifecycle', () => {
  test('click Edit, see form inputs, save, persist', async ({ loggedInPage }) => {
    await loggedInPage.goto('/admin/posts');
    await expect(loggedInPage.locator('table tbody tr').first()).toBeVisible();

    const editButton = loggedInPage
      .locator('table tbody tr')
      .first()
      .getByRole('button', { name: /edit/i })
      .first();
    await editButton.click();
    await loggedInPage.waitForURL(/\/admin\/posts\/\d+\/edit/);

    // Form fields are wired via accessible label, not name attribute.
    const titleField = loggedInPage.getByRole('textbox', { name: /^title/i });
    const slugField = loggedInPage.getByRole('textbox', { name: /^slug/i });

    await expect(titleField).toBeVisible();
    await expect(slugField).toBeVisible();

    const originalTitle = await titleField.inputValue();
    expect(originalTitle.length).toBeGreaterThan(0);
    const newTitle = `${originalTitle} EDITED-BY-E2E`;
    await titleField.fill(newTitle);

    await loggedInPage.getByRole('button', { name: /save/i }).click();
    await loggedInPage.waitForLoadState('networkidle');

    await loggedInPage.reload();
    await expect(loggedInPage.getByRole('textbox', { name: /^title/i })).toHaveValue(newTitle);
  });
});
