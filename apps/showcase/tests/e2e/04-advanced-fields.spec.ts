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

  test('PostResource create hydrates RichText (body) on the Content tab', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts/create');
    await expect(page.getByRole('heading', { name: 'Create Post' })).toBeVisible();

    // The form() is now organised into Tabs (Content + Meta), defaultTab
    // 'content'. The RichText body lives on the Content tab, which is active
    // by default, so it hydrates without any tab interaction.
    await expect(
      page.locator(
        '[data-arqel-field="body"] [contenteditable], [data-arqel-field="body"] [role="textbox"]',
      ),
    ).toBeVisible();
  });

  test('PostResource create Meta tab hydrates its fields (author/status)', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts/create');
    await expect(page.getByRole('heading', { name: 'Create Post' })).toBeVisible();

    // The Meta-tab fields are not in the DOM until the tab is activated.
    await expect(page.locator('[data-arqel-field="status"]')).toHaveCount(0);

    // Switch to the Meta tab (role="tab", accessible name "Meta").
    await page.getByRole('tab', { name: 'Meta' }).click();

    // The Meta tab carries the author + status selects in a Grid. They
    // hydrate as native <select> controls — proving the tab switch reveals
    // the Meta schema. NB: the KeyValue `meta` field is wrapped in a Group
    // gated by `visibleIf(status === 'published')`, so it intentionally does
    // NOT render on a create page (null record) — see spec 06 / 04 history.
    await expect(page.locator('[data-arqel-field="status"] select')).toBeVisible();
    await expect(page.locator('[data-arqel-field="author_id"] select')).toBeVisible();
  });
});
