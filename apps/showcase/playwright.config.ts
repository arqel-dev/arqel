import { defineConfig, devices } from '@playwright/test';

// Dual-mode base URL. When APP_BASE_URL is set the suite runs against an
// external stack (the dogfood Docker compose: localhost:8090, postgres) and
// Playwright does NOT boot its own server. Unset, it falls back to the local
// `php artisan serve` flow on 127.0.0.1:8002 (SQLite).
const baseURL = process.env.APP_BASE_URL ?? 'http://127.0.0.1:8002';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL,
    trace: 'on-first-retry',
    headless: true,
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  // Only spin up the local artisan serve when NOT pointing at an external
  // stack. Against the dogfood Docker stack the app is already running.
  webServer: process.env.APP_BASE_URL
    ? undefined
    : {
        command: 'php artisan serve --host=127.0.0.1 --port=8002',
        // Bind/probe on 127.0.0.1 (not `localhost`, which can resolve to
        // IPv6 ::1 and never connect to artisan serve in CI).
        url: 'http://127.0.0.1:8002/admin/login',
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
      },
});
