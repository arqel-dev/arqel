import { expect, test } from './fixtures';

test.describe('responsive — tables', () => {
  // Guards the latent bug: a hiddenOnMobile column hid its <td> but not its
  // <th>, so the header had one more cell than each body row at < md. Assert
  // the header cell count equals the first body row's cell count.
  test('the table header has no orphan hiddenOnMobile column at mobile width', async ({
    loggedInPage: page,
  }) => {
    await page.setViewportSize({ width: 360, height: 900 });
    await page.goto('/admin/posts', { waitUntil: 'networkidle' });
    const counts = await page.evaluate(() => {
      const table = document.querySelector('table');
      if (!table) return { ths: -1, tds: -1 };
      const ths = table.querySelectorAll('thead tr th').length;
      const firstRow = table.querySelector('tbody tr');
      const tds = firstRow ? firstRow.querySelectorAll('td').length : -1;
      return { ths, tds };
    });
    expect(counts.tds, 'no body row found').toBeGreaterThan(0);
    expect(
      counts.ths,
      `header cells (${counts.ths}) must equal body cells (${counts.tds})`,
    ).toBe(counts.tds);
  });
});
