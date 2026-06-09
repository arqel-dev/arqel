import { expect, test } from './fixtures';

/**
 * Post resource — the widest-surface Resource in the showcase. Covers the
 * table stack (list, filter, sort, search), row edit and row delete.
 *
 * Table column order is [select][Title][Status][Featured][Published][actions]:
 *   - Title    → td:nth-child(2) (sortable, searchable)
 *   - Status   → td:nth-child(3) (BadgeColumn draft/published/archived)
 * Posts are tenant-scoped; after login the current tenant is Acme.
 */
test.describe('Post resource', () => {
  test('list renders rows', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await expect(page.locator('table tbody tr').first()).toBeVisible();
    expect(await page.locator('table tbody tr').count()).toBeGreaterThan(0);
  });

  test('filter by status narrows the list to published rows', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await expect(page.locator('table tbody tr').first()).toBeVisible();

    // The Status SelectFilter is a <select> inside a <label> reading "Status".
    const statusFilter = page.locator('label', { hasText: /^Status/ }).locator('select');
    await statusFilter.selectOption('published');

    // The list re-queries via Inertia; poll until every visible Status badge
    // reads "published".
    await expect(async () => {
      const badges = await page.locator('table tbody tr td:nth-child(3)').allInnerTexts();
      expect(badges.length).toBeGreaterThan(0);
      for (const badge of badges) {
        expect(badge.trim().toLowerCase()).toBe('published');
      }
    }).toPass();
  });

  test('sorting by title changes row order', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await expect(page.locator('table tbody tr').first()).toBeVisible();

    const firstBefore = await page.locator('table tbody tr td:nth-child(2)').first().innerText();

    // The Title header is a sortable <button>. Click twice to toggle the
    // direction, guaranteeing the first row changes if more than one row.
    await page.getByRole('button', { name: 'Title' }).click();
    await page.getByRole('button', { name: 'Title' }).click();

    await expect(async () => {
      const firstAfter = await page.locator('table tbody tr td:nth-child(2)').first().innerText();
      expect(firstAfter).not.toBe(firstBefore);
    }).toPass();
  });

  test('search filters the list', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await expect(page.locator('table tbody tr').first()).toBeVisible();

    // Capture the unfiltered row count so we can assert the list narrows.
    const before = await page.locator('table tbody tr').count();

    // Pick a real title token from the first row to search for.
    const firstTitle = await page.locator('table tbody tr td:nth-child(2)').first().innerText();
    const token = firstTitle.trim().split(/\s+/)[0];

    await page.locator('input[type="search"]').fill(token);

    // The list re-queries; at least one visible row must contain the token and
    // the result set must not be larger than the unfiltered list. Titles are
    // `fake()->unique()->words(4)`, so a token match is typically a single row;
    // we assert `<= before` to stay robust against rare shared tokens.
    await expect(async () => {
      const titles = await page.locator('table tbody tr td:nth-child(2)').allInnerTexts();
      expect(titles.length).toBeGreaterThan(0);
      expect(titles.length).toBeLessThanOrEqual(before);
      expect(titles.some((t) => t.toLowerCase().includes(token.toLowerCase()))).toBe(true);
    }).toPass();
  });

  test('create page renders the form with the required Title field', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts/create');
    await expect(page.getByRole('heading', { name: 'Create Post' })).toBeVisible();

    // The Title input lives in the default Content tab and is flagged required
    // (native + schema).
    const title = page.locator('[data-arqel-field="title"] input');
    await expect(title).toBeVisible();
    // The form() is organised into Tabs (Content default + Meta); the Author
    // belongsTo select lives in the Meta tab and is not in the DOM until the tab
    // is activated. Switch to it before asserting the select renders.
    await page.getByRole('tab', { name: 'Meta' }).click();
    await expect(page.locator('[data-arqel-field="author_id"] select')).toBeVisible();
    // Save / Cancel actions render.
    await expect(page.getByRole('button', { name: 'Save' })).toBeVisible();
  });

  test('row edit opens the edit page with the title prefilled', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await expect(page.locator('table tbody tr').first()).toBeVisible();

    const firstTitle = await page.locator('table tbody tr td:nth-child(2)').first().innerText();

    // The built-in Edit/Delete row actions now live in the per-row "Actions"
    // dropdown (role="menuitem"), mirroring spec 05. Open it, then click Edit.
    await page.locator('table tbody tr').first().getByRole('button', { name: 'Actions' }).click();
    await page.getByRole('menuitem', { name: 'Edit' }).click();
    await page.waitForURL(/\/admin\/posts\/\d+\/edit/);

    // The title field is in the default Content tab, so it is prefilled and
    // visible without switching tabs.
    await expect(page.locator('[data-arqel-field="title"] input')).toHaveValue(firstTitle.trim());
  });

  test('row delete fires DELETE directly with no confirm dialog (CANDIDATE #8)', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await expect(page.locator('table tbody tr').first()).toBeVisible();
    const before = await page.locator('table tbody tr').count();

    // HONEST ENCODING of CANDIDATE #8: the dropdown Delete bypasses the required
    // ConfirmDialog and fires DELETE directly. When Round 22 routes dropdown
    // actions through ConfirmDialog, this test must be updated to click the
    // confirm button.
    //
    // The built-in Delete row action lives in the per-row "Actions" dropdown
    // (role="menuitem") because PostResource exposes 4 row actions (>3), so they
    // collapse into the ActionMenu. Selecting Delete dispatches the Inertia
    // `DELETE /admin/posts/{id}` IMMEDIATELY — even though the server-side action
    // declares requiresConfirmation(), the dropdown path never renders a
    // ConfirmDialog. This is a one-click irreversible delete.

    // Arm the network listener BEFORE clicking so we capture the dispatch.
    const deletePromise = page.waitForResponse(
      (r) => /\/admin\/posts\/\d+$/.test(r.url()) && r.request().method() === 'DELETE',
    );

    await page.locator('table tbody tr').first().getByRole('button', { name: 'Actions' }).click();
    await page.getByRole('menuitem', { name: 'Delete' }).click();

    // The DELETE fired with no dialog interaction. Inertia visits respond with a
    // redirect (303/302) back to the list.
    const deleteResponse = await deletePromise;
    expect([302, 303]).toContain(deleteResponse.status());

    // CRUCIAL: no ConfirmDialog gated the delete. There is no lingering confirm
    // alertdialog because none was ever rendered — the delete bypassed it.
    await expect(page.getByRole('alertdialog')).toHaveCount(0);

    // The list re-renders with one fewer row, confirming the bypassed delete
    // actually mutated state without any user confirmation step.
    await expect(async () => {
      const after = await page.locator('table tbody tr').count();
      expect(after).toBe(before - 1);
    }).toPass();
  });

  test('bulk delete removes selected rows', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await expect(page.locator('table tbody tr').first()).toBeVisible();

    // Count real data rows by their selection checkbox: the empty-state is a
    // single placeholder <tr> ("No records found.") with no checkbox, so this
    // excludes it both before and after the delete.
    const dataRows = page.locator('table tbody tr td input[type="checkbox"]');
    const before = await dataRows.count();
    expect(before).toBeGreaterThan(0);

    // Acme has 15 posts and defaultPerPage is 25, so every row fits on one page
    // and the header "select all" selects the whole visible list.
    await page.locator('table thead input[type="checkbox"][aria-label="Select all rows"]').check();

    // The bulk-action bar appears once rows are selected; deleteBulk() renders a
    // "Delete selected" button. Unlike the per-row dropdown delete (CANDIDATE
    // #8), the bulk delete DOES gate behind a ConfirmDialog ("Delete selected
    // records?") — fresh dist renders it correctly. Trigger it, then confirm.
    await page.getByRole('button', { name: 'Delete selected' }).click();

    // Confirm the dialog: click its "Delete" button to actually dispatch
    // `POST /admin/posts/bulk/delete` via Inertia.
    const confirmDialog = page.getByRole('dialog', { name: /Delete selected records\?/i });
    await expect(confirmDialog).toBeVisible();
    await confirmDialog.getByRole('button', { name: 'Delete' }).click();

    // After deleting every selected row the table is empty: no row checkboxes
    // remain and the empty-state placeholder is shown instead.
    await expect(async () => {
      expect(await dataRows.count()).toBe(0);
    }).toPass();
  });
});
