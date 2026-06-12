import { defineConfig, devices } from '@playwright/test';

// Dual-mode base URL. When APP_BASE_URL is set the suite runs against an
// external stack (the dogfood Docker compose: localhost:8090, postgres) and
// Playwright does NOT boot its own server. Unset, it falls back to the local
// `php artisan serve` flow on 127.0.0.1:8002 (SQLite).
const baseURL = process.env.APP_BASE_URL ?? 'http://127.0.0.1:8002';

export default defineConfig({
  testDir: './tests/e2e',
  // The responsive baseline spec is a defect-map recorder, not a gate: it
  // asserts the *current* (still-failing) per-surface gaps and is slow. It
  // carries the `@baseline` tag; exclude it by default so the CI run only
  // executes the real gates. Refresh the map on demand with
  // `playwright test --grep @baseline` (an explicit --grep overrides this).
  grepInvert: /@baseline/,
  fullyParallel: false,
  workers: 1,
  // The serial suite shares one worker, so each test also pays for the
  // `loggedInPage` fixture setup (navigate + submit + redirect). Under serial
  // load that login can retry a couple of times, which alone can approach the
  // 30s default. Give every test a 90s budget so a slow-but-valid login never
  // exhausts the per-test timeout mid-retry (which would close the page and
  // surface a spurious "browser has been closed" during setup).
  timeout: 90_000,
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
