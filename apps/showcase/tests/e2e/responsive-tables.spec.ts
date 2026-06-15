import { expect, test } from './fixtures';

test.describe('responsive — tables', () => {
  // PostResource marks the 'Words' (word_count) column hiddenOnMobile, so it
  // must be hidden on BOTH the <th> and <td> below md — exercising the header
  // fix (the <th> previously stayed visible, orphaning a header cell). At md+
  // the column reappears.
  test('a hiddenOnMobile column hides its header AND body cell in lockstep', async ({
    loggedInPage: page,
  }) => {
    await page.goto('/admin/posts', { waitUntil: 'networkidle' });

    // Mobile: the 'Words' header is hidden; header/body cell counts still match.
    await page.setViewportSize({ width: 360, height: 900 });
    await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => r(null))));
    const mobile = await page.evaluate(() => {
      const headers = Array.from(document.querySelectorAll('thead tr th'));
      const wordsTh = headers.find((th) => /words/i.test(th.textContent ?? ''));
      const visibleThs = headers.filter((th) => (th as HTMLElement).offsetParent !== null);
      const firstRow = document.querySelector('tbody tr');
      const visibleTds = firstRow
        ? Array.from(firstRow.querySelectorAll('td')).filter(
            (td) => (td as HTMLElement).offsetParent !== null,
          )
        : [];
      return {
        wordsHidden: !!wordsTh && (wordsTh as HTMLElement).offsetParent === null,
        visibleThCount: visibleThs.length,
        visibleTdCount: visibleTds.length,
      };
    });
    expect(mobile.wordsHidden, "'Words' header must be hidden on mobile").toBe(true);
    expect(
      mobile.visibleThCount,
      `visible header cells (${mobile.visibleThCount}) must equal visible body cells (${mobile.visibleTdCount})`,
    ).toBe(mobile.visibleTdCount);

    // Desktop: the 'Words' header is visible again.
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => r(null))));
    const desktopWordsVisible = await page.evaluate(() => {
      const wordsTh = Array.from(document.querySelectorAll('thead tr th')).find((th) =>
        /words/i.test(th.textContent ?? ''),
      );
      return !!wordsTh && (wordsTh as HTMLElement).offsetParent !== null;
    });
    expect(desktopWordsVisible, "'Words' header must be visible on desktop").toBe(true);
  });

  test('the index shows cards on mobile and the table on desktop', async ({
    loggedInPage: page,
  }) => {
    await page.goto('/admin/posts', { waitUntil: 'networkidle' });

    // Mobile: the table is hidden, >=1 card is visible.
    await page.setViewportSize({ width: 360, height: 900 });
    await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => r(null))));
    const mobile = await page.evaluate(() => {
      const table = document.querySelector('table');
      const tableVisible = !!table && (table as HTMLElement).offsetParent !== null;
      const cards = Array.from(document.querySelectorAll('[data-arqel-data-card]')).filter(
        (el) => (el as HTMLElement).offsetParent !== null,
      );
      return { tableVisible, cardCount: cards.length };
    });
    expect(mobile.tableVisible, 'table must be hidden on mobile').toBe(false);
    expect(mobile.cardCount, 'at least one card on mobile').toBeGreaterThan(0);

    // Desktop: the table is visible, cards are hidden.
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => r(null))));
    const desktop = await page.evaluate(() => {
      const table = document.querySelector('table');
      const tableVisible = !!table && (table as HTMLElement).offsetParent !== null;
      const cards = Array.from(document.querySelectorAll('[data-arqel-data-card]')).filter(
        (el) => (el as HTMLElement).offsetParent !== null,
      );
      return { tableVisible, cardCount: cards.length };
    });
    expect(desktop.tableVisible, 'table must be visible on desktop').toBe(true);
    expect(desktop.cardCount, 'cards must be hidden on desktop').toBe(0);
  });
});
