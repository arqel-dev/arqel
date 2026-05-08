import { expect, test } from '@playwright/test';
import { resetDatabase } from './setup';

test.describe('Login', () => {
  test.beforeEach(() => {
    resetDatabase();
  });

  test('valid credentials redirect to admin', async ({ page }) => {
    await page.goto('/admin/login');
    await page.fill('input[name="email"]', 'admin@arqel.test');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/admin(?!\/login)/);
    expect(page.url()).toMatch(/\/admin/);
    expect(page.url()).not.toMatch(/login/);
  });

  test('invalid credentials show error', async ({ page }) => {
    await page.goto('/admin/login');
    await page.fill('input[name="email"]', 'admin@arqel.test');
    await page.fill('input[name="password"]', 'wrong-password');
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle');
    expect(page.url()).toMatch(/\/admin\/login/);

    const emailValue = await page.locator('input[name="email"]').inputValue();
    expect(emailValue).toBe('admin@arqel.test');
  });
});
