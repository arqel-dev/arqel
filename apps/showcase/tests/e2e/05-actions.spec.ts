import { expect, test } from './fixtures';

/**
 * PostResource exposes custom table Actions on top of the built-in
 * Edit/Delete: a `publish` RowAction (requiresConfirmation), a
 * `change_status` RowAction (opens a form modal with a status select) and
 * an `archive` BulkAction.
 *
 * Real-DOM notes (verified against the dogfood stack):
 *  - Row actions render inside a per-row dropdown whose trigger is a button
 *    with aria-label="Actions" (text "⋯"). The menu items are role="menuitem"
 *    labelled Edit / Delete / Publish / Change Status.
 *  - Bulk actions surface in a bulk-action bar after selecting rows; the
 *    custom `archive` action is a plain button labelled "Archive".
 *
 * KNOWN GAP (see findings ledger CANDIDATE #7): activating a custom RowAction
 * (publish/change_status) opens a dialog whose body is an empty <iframe>
 * (404), so the confirmation copy / the change_status form schema does not
 * render. These tests therefore assert the action *surfaces* (menu items +
 * bulk bar) which is what reliably works, not the broken modal contents.
 */
test.describe('Custom table actions', () => {
  test('row actions dropdown exposes the custom Publish + Change Status actions', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    const firstRow = page.locator('table tbody tr').first();
    await firstRow.getByRole('button', { name: 'Actions' }).click();

    // The built-in + custom row actions all live in the same dropdown menu.
    await expect(page.getByRole('menuitem', { name: 'Edit' })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: 'Delete' })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: 'Publish' })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: 'Change Status' })).toBeVisible();
  });

  test('activating the Publish row action opens a confirmation dialog', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    // Pick a row that is not already published so Publish is enabled.
    const rows = page.locator('table tbody tr');
    const count = await rows.count();
    let target = rows.first();
    for (let i = 0; i < count; i++) {
      if (!/published/i.test(await rows.nth(i).innerText())) {
        target = rows.nth(i);
        break;
      }
    }

    await target.getByRole('button', { name: 'Actions' }).click();
    const publishItem = page.getByRole('menuitem', { name: 'Publish' });
    await expect(publishItem).toBeVisible();
    await publishItem.click();

    // requiresConfirmation() mounts a dialog (accessible role "dialog").
    await expect(page.getByRole('dialog')).toBeVisible();
  });

  test('change-status row action opens a dialog', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    await page.locator('table tbody tr').first().getByRole('button', { name: 'Actions' }).click();
    const changeItem = page.getByRole('menuitem', { name: 'Change Status' });
    await expect(changeItem).toBeVisible();
    await changeItem.click();

    // The form-backed action opens a dialog. (Its inner schema does not
    // render — CANDIDATE #7 — so we only assert the dialog surfaces.)
    await expect(page.getByRole('dialog')).toBeVisible();
  });

  test('selecting rows reveals the bulk-action bar with the custom Archive action', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    // Toggle the header "select all" checkbox to enter bulk mode.
    await page.locator('thead input[type="checkbox"], thead [role="checkbox"]').first().click();

    // The bulk-action bar shows built-in (Delete selected, Export) plus the
    // custom Archive bulk action.
    await expect(page.getByRole('button', { name: 'Archive' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Delete selected' })).toBeVisible();
  });
});
