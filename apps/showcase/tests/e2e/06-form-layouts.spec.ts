import { expect, test } from './fixtures';

/**
 * PostResource.form() organises its schema into Tabs (Form\Layout\Tabs) with
 * two tabs — Content (default) and Meta. This proves the form-layout
 * primitives serialise + render as interactive Radix tabs.
 *
 * Real-DOM notes: the tab triggers render as role="tab" with accessible
 * names "Content" / "Meta"; the active one carries aria-selected="true".
 * Each tab's fields are only mounted while that tab is active, so switching
 * tabs swaps which `[data-arqel-field]` nodes exist.
 */
test.describe('Form layout tabs', () => {
  test('the create form renders Content and Meta tabs with Content active', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts/create');
    await expect(page.getByRole('heading', { name: 'Create Post' })).toBeVisible();

    const contentTab = page.getByRole('tab', { name: 'Content' });
    const metaTab = page.getByRole('tab', { name: 'Meta' });
    await expect(contentTab).toBeVisible();
    await expect(metaTab).toBeVisible();

    // defaultTab('content') → Content is selected on load.
    await expect(contentTab).toHaveAttribute('aria-selected', 'true');
    await expect(metaTab).toHaveAttribute('aria-selected', 'false');

    // The Content tab owns the RichText body; the Meta-only status field is
    // not mounted yet.
    await expect(page.locator('[data-arqel-field="body"]')).toBeVisible();
    await expect(page.locator('[data-arqel-field="status"]')).toHaveCount(0);
  });

  test('clicking the Meta tab switches the active tab and mounts its fields', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts/create');
    await expect(page.getByRole('heading', { name: 'Create Post' })).toBeVisible();

    const contentTab = page.getByRole('tab', { name: 'Content' });
    const metaTab = page.getByRole('tab', { name: 'Meta' });

    await metaTab.click();

    await expect(metaTab).toHaveAttribute('aria-selected', 'true');
    await expect(contentTab).toHaveAttribute('aria-selected', 'false');

    // The Meta tab's status/author selects are now mounted; the Content
    // tab's body editor is gone.
    await expect(page.locator('[data-arqel-field="status"] select')).toBeVisible();
    await expect(page.locator('[data-arqel-field="body"]')).toHaveCount(0);
  });

  test('the Meta tab renders the KeyValue meta field and "Add row" appends a key/value pair', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts/create');
    await expect(page.getByRole('heading', { name: 'Create Post' })).toBeVisible();

    // The `meta` KeyValue is kept unconditionally visible in PostResource's
    // Meta tab (no longer behind a visibleIf(status==='published') Group), so
    // it renders on the create form (null record) — this is the only spec that
    // exercises a rendered fields-advanced KeyValue.
    await page.getByRole('tab', { name: 'Meta' }).click();

    const meta = page.locator('[data-arqel-field="meta"]');
    await expect(meta).toBeVisible();

    // No rows yet on a fresh create form; the "+ Add row" affordance is there.
    const addRow = meta.getByRole('button', { name: /Add .* row/i });
    await expect(addRow).toBeVisible();
    await expect(meta.getByLabel('Key 1')).toHaveCount(0);

    // Clicking it appends an editable key/value input pair.
    await addRow.click();
    await expect(meta.getByLabel('Key 1')).toBeVisible();
    await expect(meta.getByLabel('Value 1')).toBeVisible();
  });
});
