import { expect, test } from './fixtures';

/**
 * The showcase boots with @arqel-dev/realtime wired (setupEcho) against the
 * dogfood Reverb websocket server. A correctly configured Echo/Reverb client
 * must not emit connection errors to the console on a normal page load.
 */
test.describe('Realtime (Echo / Reverb)', () => {
  test('the admin page loads with no Echo/Reverb/websocket console errors', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    const consoleErrors: string[] = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });
    page.on('pageerror', (err) => {
      consoleErrors.push(`PAGEERROR: ${err.message}`);
    });

    await page.goto('/admin/posts');
    await page.waitForLoadState('networkidle');
    // Give the websocket client time to connect/handshake.
    await page.waitForTimeout(2500);

    // The page itself rendered.
    await expect(page.locator('table').first()).toBeVisible();

    // No realtime-related console errors leaked from setupEcho.
    const realtimeErrors = consoleErrors.filter((e) => /echo|reverb|websocket/i.test(e));
    expect(realtimeErrors).toEqual([]);
  });
});
