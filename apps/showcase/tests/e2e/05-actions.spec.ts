import { expect, test } from './fixtures';

/**
 * PostResource exposes custom table Actions on top of the built-in
 * Edit/Delete: a `publish` RowAction (requiresConfirmation), a
 * `change_status` RowAction (opens a form modal with a status select) and
 * an `archive` BulkAction.
 *
 * Real-DOM notes (verified against the dogfood stack):
 *  - The row has FOUR actions (Edit / Delete / Publish / Change Status),
 *    which exceeds <ActionMenu>'s inlineThreshold of 3, so they collapse
 *    into a per-row dropdown whose trigger is a button with
 *    aria-label="Actions" (text "⋯"). The menu items are role="menuitem".
 *  - Bulk actions surface in a bulk-action bar after selecting rows; the
 *    custom `archive` action is a plain button labelled "Archive".
 *
 * KNOWN FRAMEWORK BUG — Round-22 CANDIDATE #7(A):
 *   Row actions rendered in the dropdown ActionMenu (when a row exceeds the
 *   inline threshold) bypass ConfirmDialog/ActionFormModal entirely — the
 *   dropdown item's onSelect calls onInvoke(action) directly. invokeAction()
 *   then POSTs to `/arqel-dev/actions/{name}` (no action.url), an endpoint
 *   that DOES NOT EXIST in the framework → 404 (which Inertia renders into a
 *   native dialog/iframe error overlay). So `publish`/`change_status` NEVER
 *   reach their requiresConfirmation() / form([...]) UI.
 *
 *   The tests below therefore HONESTLY encode the broken state: they assert
 *   the dropdown surfaces the menuitems (true) and that activating a custom
 *   action POSTs to the missing `/arqel-dev/actions/*` endpoint and gets a
 *   404 (the real, broken behavior). When the framework is fixed in Round 22
 *   (e.g. the dropdown routes through ConfirmDialog/ActionFormModal, or the
 *   action-execution endpoint is added), THESE TESTS WILL FAIL — that is the
 *   intended regression signal forcing this spec to be updated to assert the
 *   working confirmation/form modal.
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

  test('the Publish row action POSTs to the (currently missing) /arqel-dev/actions execution endpoint — 404, see Round-22 #7A', async ({
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

    // The dropdown item bypasses requiresConfirmation()'s ConfirmDialog and
    // POSTs straight to the framework's non-existent action-execution endpoint
    // → 404. Capturing it asserts the *real* (broken) behavior (#7A).
    const responsePromise = page.waitForResponse(
      (r) => r.url().includes('/arqel-dev/actions/') && r.request().method() === 'POST',
    );
    await publishItem.click();
    const response = await responsePromise;

    expect(response.url()).toContain('/arqel-dev/actions/publish');
    expect(response.status()).toBe(404);
  });

  test('the Change Status row action POSTs to the (currently missing) /arqel-dev/actions execution endpoint — 404, see Round-22 #7A', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    await page.locator('table tbody tr').first().getByRole('button', { name: 'Actions' }).click();
    const changeItem = page.getByRole('menuitem', { name: 'Change Status' });
    await expect(changeItem).toBeVisible();

    // Same #7A path: the form-backed action never opens its ActionFormModal —
    // the dropdown item POSTs directly to the missing endpoint → 404.
    const responsePromise = page.waitForResponse(
      (r) => r.url().includes('/arqel-dev/actions/') && r.request().method() === 'POST',
    );
    await changeItem.click();
    const response = await responsePromise;

    expect(response.url()).toContain('/arqel-dev/actions/change_status');
    expect(response.status()).toBe(404);
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
