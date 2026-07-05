import { expect, test } from './fixtures';

/**
 * Auth UI — UserMenu dropdown + opt-in Profile page (account settings).
 *
 * The showcase panel enables `->login()->profile()`, so after login the
 * Topbar renders the <UserMenu> (name/email header, Profile link, theme
 * radio, Log out) and `/admin/profile` serves the account-settings page
 * inside the admin shell. The seeded admin is `admin@arqel.test`.
 *
 * These specs exercise the real end-to-end flow the unit/integration
 * tests can't: opening the dropdown, navigating to Profile via the SPA
 * link, editing name + password against the live controller/FormRequests,
 * and logging out via the Inertia POST.
 */
test.describe('Auth UI — UserMenu', () => {
  test('opens the user menu and shows the authenticated user', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin');

    const trigger = page.getByRole('button', { name: /open user menu/i });
    await expect(trigger).toBeVisible();
    await trigger.click();

    // Header shows the seeded admin's email; Profile + Log out items present.
    await expect(page.getByText('admin@arqel.test')).toBeVisible();
    await expect(page.getByRole('menuitem', { name: /^profile$/i })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: /log out/i })).toBeVisible();
  });

  test('navigates to the Profile page via the menu link', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin');
    await page.getByRole('button', { name: /open user menu/i }).click();
    await page.getByRole('menuitem', { name: /^profile$/i }).click();

    await page.waitForURL(/\/admin\/profile$/);
    // Both sections render: account data + change password.
    await expect(page.getByRole('heading', { name: /account data/i })).toBeVisible();
    await expect(page.getByRole('heading', { name: /change password/i })).toBeVisible();
  });

  test('logs out via the menu', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin');
    await page.getByRole('button', { name: /open user menu/i }).click();
    await page.getByRole('menuitem', { name: /log out/i }).click();

    // Logout POSTs and redirects back to the login form.
    await page.waitForURL(/\/admin\/login/);
    await expect(page.locator('input[name="email"]')).toBeVisible();
  });
});

test.describe('Auth UI — Profile page', () => {
  test('updates the account name', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/profile');

    const nameInput = page.locator('#profile-name');
    await expect(nameInput).toBeVisible();
    await nameInput.fill('Renamed Admin');
    await page.getByRole('button', { name: /^save$/i }).click();

    // Inertia reloads the page props; the input keeps the saved value.
    await expect(async () => {
      await expect(nameInput).toHaveValue('Renamed Admin');
    }).toPass();

    // Persisted: reload from the server and confirm.
    await page.reload();
    await expect(page.locator('#profile-name')).toHaveValue('Renamed Admin');
  });

  test('rejects a blank name with an inline error', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/profile');

    await page.locator('#profile-name').fill('');
    await page.getByRole('button', { name: /^save$/i }).click();

    // The FormRequest 'required' rule surfaces an inline field error; the
    // page stays on /admin/profile (no redirect).
    await expect(page).toHaveURL(/\/admin\/profile$/);
    await expect(page.locator('#profile-name')).toBeVisible();
  });

  test('changes the password with the correct current password', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/profile');

    await page.locator('#current-password').fill('password');
    await page.locator('#new-password').fill('new-password-123');
    await page.locator('#confirm-password').fill('new-password-123');
    await page.getByRole('button', { name: /change password/i }).click();

    // Success: stays on the profile page (the controller redirects back);
    // the password fields reset (onSuccess).
    await expect(page).toHaveURL(/\/admin\/profile$/);
    await expect(async () => {
      await expect(page.locator('#current-password')).toHaveValue('');
    }).toPass();

    // The session survived the password change (regenerate()) — we're still
    // authenticated, so a protected route still renders (no bounce to login).
    await page.goto('/admin/profile');
    await expect(page.getByRole('heading', { name: /change password/i })).toBeVisible();
  });

  test('rejects a wrong current password', async ({ loggedInPage }) => {
    const page = loggedInPage;
    await page.goto('/admin/profile');

    await page.locator('#current-password').fill('totally-wrong');
    await page.locator('#new-password').fill('another-new-pass');
    await page.locator('#confirm-password').fill('another-new-pass');
    await page.getByRole('button', { name: /change password/i }).click();

    // current_password rule fails → inline error, stays on the page.
    await expect(page).toHaveURL(/\/admin\/profile$/);
    await expect(page.locator('#current-password')).toBeVisible();
  });
});
