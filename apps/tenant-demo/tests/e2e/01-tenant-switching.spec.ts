import { expect, test } from './fixtures';

/**
 * Tenant switching via the AuthUserResolver pattern.
 *
 * The admin user belongs to two tenants (Acme, Globex), each owning 5
 * disjoint projects. After login the current tenant is Acme, so the
 * projects list is scoped to Acme's 5. Switching to Globex via the
 * Topbar `<TenantSwitcher>` persists `current_tenant_id` on the user
 * and the resolver re-scopes every subsequent request — the same list
 * now shows Globex's 5 projects, with no overlap.
 */
test.describe('Tenant switching (AuthUserResolver)', () => {
  test('list scoped to Acme → switch → list scoped to Globex', async ({ loggedInPage }) => {
    const page = loggedInPage;

    // --- Scoped to Acme ---
    await page.goto('/admin/projects');
    await expect(page.locator('table tbody tr').first()).toBeVisible();
    await expect(page.locator('table tbody tr')).toHaveCount(5);

    // Column order is [select checkbox][Name][Status][Actions]; the
    // Name cell is the 2nd column.
    const acmeNames = await page.locator('table tbody tr td:nth-child(2)').allInnerTexts();
    expect(acmeNames).toHaveLength(5);

    // Switcher trigger reflects the current tenant.
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
    await page.goto('/admin/projects');
    await expect(page.locator('table tbody tr').first()).toBeVisible();
    await expect(page.locator('table tbody tr')).toHaveCount(5);

    const globexNames = await page.locator('table tbody tr td:nth-child(2)').allInnerTexts();
    expect(globexNames).toHaveLength(5);

    // The two tenants' projects must be disjoint.
    const overlap = acmeNames.filter((name) => globexNames.includes(name));
    expect(overlap).toHaveLength(0);

    // Trigger now reflects Globex.
    await expect(trigger).toContainText(/Globex/i);
  });
});
