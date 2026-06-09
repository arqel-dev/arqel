import { expect, test } from './fixtures';

/**
 * PostResource.table() declares two non-trivial column types beyond the
 * plain TextColumns:
 *   - a ComputedColumn('word_count')->label('Words') whose state is derived
 *     at serialization via getStateUsing();
 *   - a RelationshipColumn('author')->display('name')->label('Author') that
 *     traverses the belongsTo author relation.
 *
 * Both must surface as visible column headers on the index table, proving
 * the column types serialise into the React table schema.
 */
test.describe('Advanced table columns', () => {
  test('the posts table renders the Words (computed) and Author (relationship) headers', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    const table = page.locator('table').first();
    await expect(table).toBeVisible();

    // Column headers are rendered in the table head.
    await expect(table.locator('thead').getByText('Words', { exact: true })).toBeVisible();
    await expect(table.locator('thead').getByText('Author', { exact: true })).toBeVisible();
  });

  test('the Words computed column produces a numeric value per row', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');

    // Resolve the Words column index from the header order, then assert the
    // first data row's cell at that index is a number (word count).
    const headers = await page.locator('table thead th').allInnerTexts();
    const wordsIdx = headers.findIndex((h) => /^words$/i.test(h.trim()));
    expect(wordsIdx).toBeGreaterThanOrEqual(0);

    const cell = page.locator('table tbody tr').first().locator('td').nth(wordsIdx);
    await expect(cell).toBeVisible();
    expect((await cell.innerText()).trim()).toMatch(/^\d+$/);
  });
});
