import { test as base, expect, type Page } from '@playwright/test';
import { resetDatabase } from './setup';

interface Fixtures {
  loggedInPage: Page;
}

export const test = base.extend<Fixtures>({
  loggedInPage: async ({ page }, use) => {
    resetDatabase();
    await page.goto('/admin/login');
    await page.fill('input[name="email"]', 'admin@arqel.test');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/admin(?!\/login)/);
    await use(page);
  },
});

export { expect };
