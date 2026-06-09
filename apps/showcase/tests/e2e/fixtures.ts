import { test as base, expect, type Page } from '@playwright/test';
import { resetDatabase } from './setup';

interface Fixtures {
  loggedInPage: Page;
}

/**
 * Navigate to the login page and wait for the React form to hydrate.
 *
 * `php artisan serve` is single-process and, under Chromium's
 * concurrent/aborted connections, occasionally flushes a transient
 * `file_put_contents(): … Broken pipe` notice from its `server.php`
 * router instead of the real HTML. The condition is non-deterministic
 * and clears on a reload, so we retry the navigation a few times until
 * the email field is actually present. This is a dev-server quirk, not
 * an app defect — the recovered response is the correct login page.
 */
async function gotoLogin(page: Page): Promise<void> {
  for (let attempt = 0; attempt < 5; attempt++) {
    await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });
    try {
      await page.locator('input[name="email"]').waitFor({ state: 'visible', timeout: 5000 });
      return;
    } catch {
      // Transient broken-pipe response — retry the navigation.
    }
  }
  // Final attempt: let the normal assertion/timeout surface a real failure.
  await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="email"]').waitFor({ state: 'visible' });
}

/**
 * Submit the login form and wait for the post-login redirect off /admin/login.
 *
 * Under load the single-process `php artisan serve` can be slow to issue the
 * redirect, so `waitForURL` occasionally times out at the default 5s even
 * though the credentials are valid. We retry the submit→wait a couple of times
 * (mirroring `gotoLogin`'s broken-pipe retry) with a longer per-attempt
 * timeout. This only hardens against load flakiness — it does not change the
 * login semantics (same credentials, same success condition).
 */
async function submitLogin(page: Page): Promise<void> {
  for (let attempt = 0; attempt < 3; attempt++) {
    await page.fill('input[name="email"]', 'admin@arqel.test');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    try {
      await page.waitForURL(/\/admin(?!\/login)/, { timeout: 15000 });
      return;
    } catch {
      // Still on the login page under load — re-navigate and retry the submit.
      if (attempt < 2) {
        await gotoLogin(page);
      }
    }
  }
  // Final attempt: let the assertion/timeout surface a real failure.
  await page.fill('input[name="email"]', 'admin@arqel.test');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/admin(?!\/login)/, { timeout: 15000 });
}

export const test = base.extend<Fixtures>({
  loggedInPage: async ({ page }, use) => {
    resetDatabase();
    await gotoLogin(page);
    await submitLogin(page);
    await use(page);
  },
});

export { expect };
