import { expect, test } from './fixtures';

test.describe('Row delete with confirmation', () => {
  test('click Delete, confirm modal, record removed', async ({ loggedInPage }) => {
    await loggedInPage.goto('/admin/posts');
    await expect(loggedInPage.locator('table tbody tr').first()).toBeVisible();

    const totalBefore = await loggedInPage.locator('table tbody tr').count();

    const deleteButton = loggedInPage
      .locator('table tbody tr')
      .first()
      .getByRole('button', { name: /delete/i })
      .first();
    await deleteButton.click();

    const modal = loggedInPage.getByRole('dialog');
    await expect(modal).toBeVisible();

    const confirmButton = modal.getByRole('button', { name: /delete|confirm/i }).last();
    await confirmButton.click();

    await loggedInPage.waitForLoadState('networkidle');

    const totalAfter = await loggedInPage.locator('table tbody tr').count();
    expect(totalAfter).toBe(totalBefore - 1);
  });
});
