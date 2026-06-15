import { expect, test } from './fixtures';
import { assertNoHorizontalOverflow, forEachViewport, shot } from './responsive';

test.describe('responsive — shell', () => {
  test('the resource index page does not overflow horizontally at any viewport', async ({
    loggedInPage: page,
  }) => {
    await page.goto('/admin/posts');
    await forEachViewport(page, async (w) => {
      await shot(page, 'shell-posts', w);
      // The shell wraps every page; the table must scroll within its own
      // overflow-x-auto wrapper, not push the body wide. (#SidebarInset min-w-0)
      await assertNoHorizontalOverflow(page);
    });
  });

  // Regression guard for the CI-only topbar overflow: the runner's wider system
  // font pushed the topbar's shrink-0 control cluster ~13px past 360, and an
  // unconstrained <header> propagated that to the body (scrollWidth 373 > 360).
  // The fix gives the topbar `min-w-0 overflow-hidden`. Reproduce the mechanism
  // deterministically (font-independent) by injecting an over-wide, non-shrinking
  // node into the bar and asserting the body still does not overflow.
  test('an over-wide topbar is clipped and never pushes the body wide on mobile', async ({
    loggedInPage: page,
  }) => {
    await page.setViewportSize({ width: 360, height: 900 });
    await page.goto('/admin/posts', { waitUntil: 'networkidle' });
    await page.evaluate(() => {
      const header = document.querySelector('header[data-arqel-topbar]');
      if (!header) throw new Error('topbar not found');
      const cluster = header.querySelector('.ml-auto') ?? header;
      const probe = document.createElement('span');
      probe.textContent = 'W'.repeat(40);
      probe.style.whiteSpace = 'nowrap';
      probe.style.flexShrink = '0';
      cluster.appendChild(probe);
    });
    await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => r(null))));
    const body = await page.evaluate(() => ({
      scrollWidth: document.body.scrollWidth,
      clientWidth: document.body.clientWidth,
    }));
    expect(
      body.scrollWidth,
      `over-wide topbar leaked to the body: scrollWidth=${body.scrollWidth} > clientWidth=${body.clientWidth}`,
    ).toBeLessThanOrEqual(body.clientWidth + 1);
  });
});
