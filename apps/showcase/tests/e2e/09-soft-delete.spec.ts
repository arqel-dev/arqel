import { expect, test } from './fixtures';

/**
 * OrderResource is backed by a soft-deleting model. The seeder creates 20
 * active + 5 trashed orders. By default the index excludes trashed rows, and
 * a `trashed` SelectFilter (Filament-style: All / With trashed / Only
 * trashed) lets the operator widen the scope.
 *
 * Real-DOM notes (verified against the dogfood stack):
 *  - SelectFilters render as a labelled native <select>; the trashed filter's
 *    <label> text is "Trashed" with options All / With trashed / Only trashed.
 *  - The default (active-only) table shows 20 rows.
 */
test.describe('Soft-delete resource (Orders)', () => {
  test('the orders table renders and excludes trashed rows by default', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/orders');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('table').first()).toBeVisible();
    // 20 active orders seeded; the 5 trashed are excluded by default.
    await expect(page.locator('table tbody tr')).toHaveCount(20);
  });

  test('the trashed SelectFilter is present with its scope options', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/orders');
    await page.waitForLoadState('networkidle');

    // The filter is a native <select> labelled "Trashed".
    const trashedSelect = page.locator('select').filter({ hasText: 'Only trashed' });
    await expect(trashedSelect).toBeVisible();
    const opts = await trashedSelect.locator('option').allInnerTexts();
    expect(opts.join('|')).toContain('With trashed');
    expect(opts.join('|')).toContain('Only trashed');
  });

  test('selecting "Only trashed" surfaces the soft-deleted orders', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/orders');
    await page.waitForLoadState('networkidle');

    const trashedSelect = page.locator('select').filter({ hasText: 'Only trashed' });
    await trashedSelect.selectOption({ label: 'Only trashed' });

    // The 5 soft-deleted orders are now the only rows.
    await expect(page.locator('table tbody tr')).toHaveCount(5);
  });
});
