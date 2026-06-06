import { expect, test } from './fixtures';

/**
 * Tenant switching via the AuthUserResolver pattern, adapted to the
 * showcase Post resource.
 *
 * The admin user belongs to two tenants (Acme, Globex). `Post` uses
 * `BelongsToTenant`, so `/admin/posts` is scoped to the current tenant.
 * After login the current tenant is Acme (the seeder splits ~30 posts
 * even/odd across the two tenants), so the list shows only Acme's posts.
 * Switching to Globex via the Topbar `<TenantSwitcher>` persists
 * `current_tenant_id` and re-scopes every subsequent request — the same
 * list now shows Globex's posts, disjoint from Acme's by title.
 */
test.describe('Tenant switching (AuthUserResolver)', () => {
  test('posts scoped to Acme → switch → scoped to Globex (disjoint)', async ({ loggedInPage }) => {
    const page = loggedInPage;

    // --- Scoped to Acme ---
    await page.goto('/admin/posts');
    await expect(page.locator('table tbody tr').first()).toBeVisible();

    // Column order is [select][Title][Status][Featured][Published][actions];
    // the Title cell is the 2nd column.
    const acmeTitles = await page.locator('table tbody tr td:nth-child(2)').allInnerTexts();
    expect(acmeTitles.length).toBeGreaterThan(0);

    const trigger = page.locator('[data-testid="tenant-switcher-trigger"]');
    await expect(trigger).toContainText(/Acme/i);

    // --- Switch to Globex ---
    await trigger.click();
    await page
      .locator('[data-testid^="tenant-switcher-option-"]')
      .filter({ hasText: /Globex/i })
      .click();

    // Inertia POST → controller updates current_tenant_id → redirect.
    await page.waitForURL(/\/admin(?!\/login)/);

    // --- Scoped to Globex ---
    await page.goto('/admin/posts');
    await expect(page.locator('table tbody tr').first()).toBeVisible();

    const globexTitles = await page.locator('table tbody tr td:nth-child(2)').allInnerTexts();
    expect(globexTitles.length).toBeGreaterThan(0);

    // The two tenants' posts must be disjoint.
    const overlap = acmeTitles.filter((title) => globexTitles.includes(title));
    expect(overlap).toHaveLength(0);

    // Trigger now reflects Globex.
    await expect(trigger).toContainText(/Globex/i);
  });
});
