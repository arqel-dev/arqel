import { expect, test } from './fixtures';

/**
 * RelationManager (0.18) — PostResource declares two relation managers
 * (`App\Arqel\Relations\CommentsRelationManager` / `CategoriesRelationManager`),
 * both on real, seeded Eloquent relations:
 *   - `Post::comments()` is hasMany (1-3 seeded `Comment`s per post) — covers
 *     the create/edit/delete flow via `RelationFormModal`.
 *   - `Post::categories()` is belongsToMany (1-3 seeded `Category`s per post,
 *     pivot `category_post`) — covers the attach flow via `AttachModal`.
 *
 * UI notes (verified against source, not the running dogfood stack):
 *  - `ResourceEditTabs` renders a `role="tablist"` with a "Data" tab plus one
 *    tab per relation, labelled via `Str::headline(slug)`: "Comments" and
 *    "Categories" (`packages-js/ui/src/relations/ResourceEditTabs.tsx`).
 *  - `RelationManagerPanel` self-fetches
 *    `GET /admin/posts/{id}/relations/{slug}` on mount/refresh — no Inertia
 *    partial reload backs the table, so assertions must poll (`toPass()`)
 *    rather than assume a synchronous re-render.
 *  - Toolbar buttons are ability-gated plain `<Button>`s with literal text:
 *    "New" (create, both relations) and "Attach" (belongsToMany only,
 *    i.e. only on the Categories tab). Per-row "Edit" (ghost button) shows
 *    when `abilities.update` is true.
 *  - `RelationFormModal` is a shadcn `Dialog` (`role="dialog"`) whose title
 *    is the relation label ("Comments"); its single field renders as
 *    `[data-arqel-field="body"] textarea` (see `CommentsRelationManager`,
 *    which declares `Field::textarea('body')->required()`); submit button
 *    reads "Save" (`FormActions` fallback), cancel reads "Cancel".
 *  - `AttachModal` dialog title is "Attach Categories"; the picker is a
 *    plain id-entry `<input>` labelled with the relation label ("Categories")
 *    — NOT a search combobox (documented Phase-1 limitation in the
 *    component's own docblock) — so this spec looks up a real Category id
 *    via the API response before typing it in. Submit button reads "Attach".
 *  - There is currently NO visible "Detach" control in `RelationManagerPanel`
 *    (only the server-side `RelationController::detach()` endpoint exists;
 *    the React panel doesn't wire a per-row Detach action yet). Detach
 *    E2E coverage is therefore DEFERRED until that control ships — see the
 *    task report for details. This spec only covers attach.
 */
test.describe('RelationManager (Post → Comments/Categories)', () => {
  test('opens a Post edit page and lists relation tabs', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await expect(page.locator('table tbody tr').first()).toBeVisible();

    await page.locator('table tbody tr').first().getByRole('button', { name: 'Actions' }).click();
    await page.getByRole('menuitem', { name: 'Edit' }).click();
    await page.waitForURL(/\/admin\/posts\/\d+\/edit/);

    await expect(page.getByRole('tab', { name: 'Data' })).toBeVisible();
    await expect(page.getByRole('tab', { name: 'Comments' })).toBeVisible();
    await expect(page.getByRole('tab', { name: 'Categories' })).toBeVisible();
  });

  test('Comments tab: list loads, create, edit and delete a comment', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await expect(page.locator('table tbody tr').first()).toBeVisible();

    await page.locator('table tbody tr').first().getByRole('button', { name: 'Actions' }).click();
    await page.getByRole('menuitem', { name: 'Edit' }).click();
    await page.waitForURL(/\/admin\/posts\/\d+\/edit/);

    await page.getByRole('tab', { name: 'Comments' }).click();
    const commentsPanel = page.getByRole('tabpanel', { name: 'Comments' });

    // Seeded posts carry 1-3 comments, so the self-fetched table should
    // populate with at least one row once the fetch resolves.
    await expect(async () => {
      expect(await commentsPanel.locator('table tbody tr').count()).toBeGreaterThan(0);
    }).toPass();

    const before = await commentsPanel.locator('table tbody tr').count();

    // Create.
    await commentsPanel.getByRole('button', { name: 'New' }).click();
    const createDialog = page.getByRole('dialog');
    await expect(createDialog).toBeVisible();
    await expect(createDialog.getByRole('heading', { name: 'Comments' })).toBeVisible();

    const newBody = `E2E comment ${Date.now()}`;
    await createDialog.locator('[data-arqel-field="body"] textarea').fill(newBody);
    await createDialog.getByRole('button', { name: 'Save' }).click();
    await expect(createDialog).not.toBeVisible();

    // The panel refetches after a successful create (refreshKey bump); the
    // new row should appear once that resolves.
    await expect(async () => {
      expect(await commentsPanel.locator('table tbody tr').count()).toBe(before + 1);
      await expect(commentsPanel.locator('table tbody tr', { hasText: newBody })).toBeVisible();
    }).toPass();

    // Edit.
    const newRow = commentsPanel.locator('table tbody tr', { hasText: newBody });
    await newRow.getByRole('button', { name: 'Edit' }).click();
    const editDialog = page.getByRole('dialog');
    await expect(editDialog).toBeVisible();

    const editedBody = `${newBody} (edited)`;
    await editDialog.locator('[data-arqel-field="body"] textarea').fill(editedBody);
    await editDialog.getByRole('button', { name: 'Save' }).click();
    await expect(editDialog).not.toBeVisible();

    await expect(async () => {
      await expect(commentsPanel.locator('table tbody tr', { hasText: editedBody })).toBeVisible();
    }).toPass();

    // Delete via the RelationController DELETE endpoint. The panel does not
    // (yet) render a per-row Delete button, so this exercises the server
    // contract directly via an authenticated fetch from the page context,
    // then confirms the panel reflects the removal on its next refetch
    // (triggered here by reloading the tab).
    const postIdMatch = page.url().match(/\/admin\/posts\/(\d+)\/edit/);
    const postId = postIdMatch?.[1];
    expect(postId).toBeTruthy();

    const rowCells = await commentsPanel
      .locator('table tbody tr', { hasText: editedBody })
      .locator('td')
      .allInnerTexts();
    expect(rowCells.length).toBeGreaterThan(0);

    // Fetch the relation's current records to resolve the created comment's id.
    const records = await page.evaluate(async (args) => {
      const res = await fetch(`/admin/posts/${args.postId}/relations/comments`, {
        headers: { Accept: 'application/json' },
      });
      return (await res.json()) as { records: Array<{ id: number; body: string }> };
    }, { postId });
    const target = records.records.find((r) => r.body === editedBody);
    expect(target).toBeTruthy();

    await page.evaluate(
      async (args) => {
        const token = document
          .querySelector('meta[name="csrf-token"]')
          ?.getAttribute('content');
        await fetch(`/admin/posts/${args.postId}/relations/comments/${args.id}`, {
          method: 'DELETE',
          headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': token ?? '',
          },
        });
      },
      { postId, id: target?.id },
    );

    // Force the panel to refetch by switching tabs away and back.
    await page.getByRole('tab', { name: 'Data' }).click();
    await page.getByRole('tab', { name: 'Comments' }).click();

    await expect(async () => {
      await expect(commentsPanel.locator('table tbody tr', { hasText: editedBody })).toHaveCount(0);
      expect(await commentsPanel.locator('table tbody tr').count()).toBe(before);
    }).toPass();
  });

  test('Categories tab: attach an existing category, row appears, category survives', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/posts');
    await expect(page.locator('table tbody tr').first()).toBeVisible();

    await page.locator('table tbody tr').first().getByRole('button', { name: 'Actions' }).click();
    await page.getByRole('menuitem', { name: 'Edit' }).click();
    await page.waitForURL(/\/admin\/posts\/\d+\/edit/);

    const postIdMatch = page.url().match(/\/admin\/posts\/(\d+)\/edit/);
    const postId = postIdMatch?.[1];
    expect(postId).toBeTruthy();

    await page.getByRole('tab', { name: 'Categories' }).click();
    const categoriesPanel = page.getByRole('tabpanel', { name: 'Categories' });

    await expect(async () => {
      expect(await categoriesPanel.locator('table tbody tr').count()).toBeGreaterThan(0);
    }).toPass();

    const before = await categoriesPanel.locator('table tbody tr').count();

    // There's no standalone Category Resource/route in the showcase to browse
    // all categories, so resolve an id NOT already attached to this post by
    // scanning the seeded id space (`DatabaseSeeder` creates exactly 10
    // categories, each post attaches 1-3, so an unattached id is virtually
    // guaranteed). Read the currently-attached ids straight from the
    // relation's own fetch response rather than the rendered table (which
    // only exposes `name`, not `id`).
    const currentRecords = await page.evaluate(async (args) => {
      const res = await fetch(`/admin/posts/${args.postId}/relations/categories`, {
        headers: { Accept: 'application/json' },
      });
      return (await res.json()) as { records: Array<{ id: number; name: string }> };
    }, { postId });
    const attachedIds = new Set(currentRecords.records.map((r) => r.id));
    const candidateIds = Array.from({ length: 10 }, (_, i) => i + 1);
    const unattachedId = candidateIds.find((id) => !attachedIds.has(id));
    expect(unattachedId).toBeTruthy();

    await categoriesPanel.getByRole('button', { name: 'Attach' }).click();
    const attachDialog = page.getByRole('dialog');
    await expect(attachDialog).toBeVisible();
    await expect(attachDialog.getByRole('heading', { name: 'Attach Categories' })).toBeVisible();

    await attachDialog.getByLabel('Categories').fill(String(unattachedId));
    await attachDialog.getByRole('button', { name: 'Attach' }).click();
    await expect(attachDialog).not.toBeVisible();

    await expect(async () => {
      expect(await categoriesPanel.locator('table tbody tr').count()).toBe(before + 1);
    }).toPass();

    // The category record itself must survive the attach (it's a pivot link,
    // not a create) — confirm a full page reload still shows it attached, i.e.
    // it's a real persisted DB row and not a client-only optimistic artifact.
    await page.reload();
    await page.getByRole('tab', { name: 'Categories' }).click();
    const reloadedRecords = await page.evaluate(async (args) => {
      const res = await fetch(`/admin/posts/${args.postId}/relations/categories`, {
        headers: { Accept: 'application/json' },
      });
      return (await res.json()) as { records: Array<{ id: number; name: string }> };
    }, { postId });
    expect(reloadedRecords.records.some((r) => r.id === unattachedId)).toBe(true);
    expect(reloadedRecords.records.length).toBe(before + 1);
  });
});
