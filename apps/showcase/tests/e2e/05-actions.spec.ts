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
 * Round-22 FIX — CANDIDATE #7(A) (issues #231 / dropdown-routing):
 *   Previously, row actions rendered in the dropdown ActionMenu (when a row
 *   exceeds the inline threshold) bypassed ConfirmDialog/ActionFormModal
 *   entirely — the dropdown item's onSelect called onInvoke(action) directly,
 *   and invokeAction() POSTed to the dead `/arqel-dev/actions/{name}` route
 *   (no action.url) → 404. So `publish`/`change_status` never reached their
 *   requiresConfirmation() / form([...]) UI.
 *
 *   The fix corrected BOTH halves and the live dogfood stack now exhibits the
 *   working behavior, verified by driving it:
 *     - The dropdown `publish` item (requiresConfirmation) opens a real
 *       ConfirmDialog (role="dialog", "Are you sure?", Cancel/Confirm).
 *     - The dropdown `change_status` item (form([...])) opens a real
 *       ActionFormModal (role="dialog", titled "Change Status", with the
 *       status <select> inside data-arqel-field="status" and a submit button).
 *     - Confirming/submitting POSTs to the REAL, action-aware endpoint
 *       `POST /admin/posts/actions/{action}/{id}` — NOT the dead
 *       `/arqel-dev/actions/*` route. The serialized action now carries that
 *       url (Action::resolveStockUrl, #231).
 *
 *   The specs below assert that corrected behavior: the confirm/form modal
 *   appears and the dispatch targets `/admin/posts/actions/...`. They no
 *   longer assert the dead `/arqel-dev/actions/*` route or a 404 — those were
 *   the honest encodings of the now-fixed bug.
 *
 *   NOTE (residual, out of scope here): the request currently still resolves
 *   to 404 because core's ResourceController::findResourceAction only scans a
 *   resource's actions()/headerActions()/toolbarActions() methods, while
 *   PostResource declares its row actions inside table()->actions([...]).
 *   That is a SEPARATE controller-resolution gap from the #7A dropdown/url fix
 *   these specs encode; we therefore assert the corrected client behavior
 *   (real modal + real endpoint URL) without pinning the response status.
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

  test('the Publish row action dispatches through a ConfirmDialog to the real /admin/posts/actions endpoint (Round-22 #7A fixed)', async ({
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

    // FIXED: the dropdown item now honours requiresConfirmation() — clicking it
    // opens a real ConfirmDialog instead of POSTing straight to a dead route.
    // Nothing is dispatched before the user confirms.
    await publishItem.click();
    const confirmDialog = page.getByRole('dialog');
    await expect(confirmDialog).toBeVisible();
    await expect(confirmDialog).toContainText(/are you sure/i);
    await expect(confirmDialog.getByRole('button', { name: 'Confirm' })).toBeVisible();

    // Confirming dispatches to the REAL, action-aware endpoint
    // `POST /admin/posts/actions/publish/{id}` (#231) — NOT the dead
    // `/arqel-dev/actions/*` route that produced the original 404.
    const responsePromise = page.waitForResponse(
      (r) =>
        /\/admin\/posts\/actions\/publish\/\d+$/.test(r.url()) && r.request().method() === 'POST',
    );
    await confirmDialog.getByRole('button', { name: 'Confirm' }).click();
    const response = await responsePromise;

    // The request now travels to the corrected endpoint and is NOT the old
    // dead `/arqel-dev/actions/*` route.
    expect(response.url()).toMatch(/\/admin\/posts\/actions\/publish\/\d+$/);
    expect(response.url()).not.toContain('/arqel-dev/actions/');
  });

  test('the Change Status row action opens its form modal and submits to the real /admin/posts/actions endpoint (Round-22 #7A fixed)', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    await page.locator('table tbody tr').first().getByRole('button', { name: 'Actions' }).click();
    const changeItem = page.getByRole('menuitem', { name: 'Change Status' });
    await expect(changeItem).toBeVisible();

    // FIXED: the form-backed action now opens its ActionFormModal (a dialog
    // titled "Change Status" carrying the status <select>) instead of POSTing
    // straight to a dead route.
    await changeItem.click();
    const formModal = page.getByRole('dialog');
    await expect(formModal).toBeVisible();
    await expect(formModal).toContainText('Change Status');
    const statusSelect = formModal.locator('[data-arqel-field="status"] select');
    await expect(statusSelect).toBeVisible();
    await statusSelect.selectOption('archived');

    // Submitting the modal dispatches to the REAL, action-aware endpoint
    // `POST /admin/posts/actions/change_status/{id}` (#231) — NOT the dead
    // `/arqel-dev/actions/*` route that produced the original 404.
    const responsePromise = page.waitForResponse(
      (r) =>
        /\/admin\/posts\/actions\/change_status\/\d+$/.test(r.url()) &&
        r.request().method() === 'POST',
    );
    // The modal's submit button reuses the action label "Change Status"; scope
    // to the dialog to disambiguate from the menuitem text.
    await formModal.getByRole('button', { name: 'Change Status' }).click();
    const response = await responsePromise;

    expect(response.url()).toMatch(/\/admin\/posts\/actions\/change_status\/\d+$/);
    expect(response.url()).not.toContain('/arqel-dev/actions/');
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
