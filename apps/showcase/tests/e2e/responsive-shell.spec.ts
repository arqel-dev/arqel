import { test } from './fixtures';
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
});
