import { expect, test } from './fixtures';

/**
 * The VersionsDemo page (GET /admin/versions-demo) mounts the
 * @arqel-dev/versioning VersionHistoryDrawer with two demo versions: an
 * initial (non-restorable) version and a regular edit (restorable).
 *
 * Real-DOM notes (verified against the dogfood stack):
 *  - The drawer wrapper carries data-testid="version-history-drawer".
 *  - VersionTimeline renders a "Compare" button per version and a "Restore"
 *    button only for restorable versions (canRestore => !is_initial). With
 *    one restorable + one initial version that yields two "Compare" buttons
 *    and exactly one "Restore" button.
 *
 * NB: the route is registered in AppServiceProvider::register() rather than
 * routes/web.php — Arqel's greedy `admin/{resource}` route would otherwise
 * shadow it and 404. See the findings ledger CANDIDATE #7.
 */
test.describe('Versioning UI (VersionHistoryDrawer)', () => {
  test('the versions-demo page mounts the drawer with a timeline', async ({ loggedInPage }) => {
    const page = loggedInPage;
    const resp = await page.goto('/admin/versions-demo');
    expect(resp?.status()).toBe(200);
    await page.waitForLoadState('networkidle');

    const drawer = page.locator('[data-testid="version-history-drawer"]');
    await expect(drawer).toBeVisible();

    // The timeline renders Compare + Restore controls.
    await expect(drawer.getByRole('button', { name: 'Compare' }).first()).toBeVisible();
    await expect(drawer.getByRole('button', { name: 'Restore' })).toBeVisible();
  });

  test('the initial version is not restorable (no extra Restore button)', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/versions-demo');
    await page.waitForLoadState('networkidle');

    const drawer = page.locator('[data-testid="version-history-drawer"]');
    // Two versions → two Compare buttons; only the non-initial one is
    // restorable → exactly one Restore button.
    await expect(drawer.getByRole('button', { name: 'Compare' })).toHaveCount(2);
    await expect(drawer.getByRole('button', { name: 'Restore' })).toHaveCount(1);
  });
});
