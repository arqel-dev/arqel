import { expect, test } from './fixtures';

/**
 * Advanced field components must hydrate on the create pages, proving the
 * `@arqel-dev/fields-advanced` React bundle is wired into the FieldRegistry.
 *
 * SettingResource.create exercises Repeater (items), Tags (tags), Code
 * (snippet, json) and Markdown (notes). PostResource.create exercises
 * RichText (body) + KeyValue (meta).
 *
 * Components are asserted by their distinctive inner controls:
 *   - Repeater → an "Add item" button (aria-label="Add item")
 *   - Tags     → role="combobox" input
 *   - Code     → data-testid="code-gutter"
 *   - Markdown → a textarea labelled by the field label
 *   - RichText → an aria-label'd editable region with a toolbar
 *   - KeyValue → key/value row inputs with an add control
 */
test.describe('Advanced fields render', () => {
  test('SettingResource create hydrates Repeater, Tags, Code, Markdown', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/settings/create');
    await expect(page.getByRole('heading', { name: 'Create Setting' })).toBeVisible();

    // Repeater: the add-item control.
    await expect(page.getByRole('button', { name: 'Add item' })).toBeVisible();

    // Tags: a combobox input inside the tags field.
    await expect(page.locator('[data-arqel-field="tags"] [role="combobox"]')).toBeVisible();

    // Code editor: the gutter that CodeInput renders.
    await expect(
      page.locator('[data-arqel-field="snippet"] [data-testid="code-gutter"]'),
    ).toBeVisible();

    // Markdown: a textarea inside the notes field.
    await expect(page.locator('[data-arqel-field="notes"] textarea')).toBeVisible();
  });

  test('PostResource create hydrates RichText (body) + KeyValue (meta)', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts/create');
    await expect(page.getByRole('heading', { name: 'Create Post' })).toBeVisible();

    // RichText: an editable region (contenteditable) inside the body field.
    await expect(
      page.locator(
        '[data-arqel-field="body"] [contenteditable], [data-arqel-field="body"] [role="textbox"]',
      ),
    ).toBeVisible();

    // KeyValue: hydrates with no rows, so its distinctive control is the
    // "Add row" button. Clicking it must materialise a key/value input pair,
    // proving the component is interactive (not a label-only fallback).
    const meta = page.locator('[data-arqel-field="meta"]');
    const addRow = meta.getByRole('button', { name: /add .*row/i });
    await expect(addRow).toBeVisible();
    await addRow.click();
    await expect(meta.locator('input').first()).toBeVisible();
  });
});
