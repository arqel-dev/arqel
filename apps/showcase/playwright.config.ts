import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: 'http://127.0.0.1:8002',
    trace: 'on-first-retry',
    headless: true,
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: {
    command: 'php artisan serve --host=127.0.0.1 --port=8002',
    // Bind/probe on 127.0.0.1 (not `localhost`, which can resolve to
    // IPv6 ::1 and never connect to artisan serve in CI).
    url: 'http://127.0.0.1:8002/admin/login',
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});
