import { expect, type Page } from '@playwright/test';

/** The 5 target viewports (px widths) the loop measures at. */
export const VIEWPORTS = [360, 640, 768, 1024, 1440] as const;
export type ViewportWidth = (typeof VIEWPORTS)[number];

/** The mobile widths where touch-target rules apply. */
export const MOBILE_WIDTHS: ViewportWidth[] = [360, 640];

/**
 * Set the viewport to `width` (height 900), wait for the layout to settle
 * (network idle + fonts loaded + a paint frame), then run `fn`.
 */
export async function atViewport(
  page: Page,
  width: ViewportWidth,
  fn: () => Promise<void>,
): Promise<void> {
  await page.setViewportSize({ width, height: 900 });
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => (document as Document).fonts?.ready);
  await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => r(null))));
  await fn();
}

/** Run `fn` at every target viewport, labelling failures by width. */
export async function forEachViewport(
  page: Page,
  fn: (width: ViewportWidth) => Promise<void>,
): Promise<void> {
  for (const width of VIEWPORTS) {
    await atViewport(page, width, () => fn(width));
  }
}

/**
 * Assert no horizontal overflow on the document (and optionally a selector):
 * `scrollWidth <= clientWidth + tolerance`. A 1px tolerance absorbs sub-pixel
 * rounding. This is the highest-signal responsiveness metric.
 */
export async function assertNoHorizontalOverflow(
  page: Page,
  selector = 'body',
  tolerance = 1,
): Promise<void> {
  const overflow = await page.evaluate(
    ({ sel, tol }) => {
      const el = sel === 'body' ? document.body : document.querySelector(sel);
      if (!el) return { found: false, scrollWidth: 0, clientWidth: 0, tol };
      return { found: true, scrollWidth: el.scrollWidth, clientWidth: el.clientWidth, tol };
    },
    { sel: selector, tol: tolerance },
  );
  expect(overflow.found, `selector "${selector}" not found`).toBe(true);
  expect(
    overflow.scrollWidth,
    `"${selector}" overflows horizontally: scrollWidth=${overflow.scrollWidth} > clientWidth=${overflow.clientWidth}`,
  ).toBeLessThanOrEqual(overflow.clientWidth + tolerance);
}

/**
 * Assert each matched element's bounding box is within [0, viewportWidth]:
 * nothing clipped off the right edge, nothing zero-width (collapsed/hidden-broken).
 */
export async function assertWithinViewport(page: Page, selector: string): Promise<void> {
  const vw = page.viewportSize()?.width ?? 0;
  const boxes = await page.locator(selector).evaluateAll((els) =>
    els.map((el) => {
      const r = el.getBoundingClientRect();
      return { left: r.left, right: r.right, width: r.width, height: r.height };
    }),
  );
  for (const [i, b] of boxes.entries()) {
    // Skip genuinely hidden elements (0x0) — only flag visible-but-clipped.
    if (b.width === 0 && b.height === 0) continue;
    expect(
      b.right,
      `"${selector}"[${i}] clipped off the right edge (right=${b.right} > ${vw})`,
    ).toBeLessThanOrEqual(vw + 1);
    expect(
      b.left,
      `"${selector}"[${i}] starts off the left edge (left=${b.left})`,
    ).toBeGreaterThanOrEqual(-1);
  }
}

/**
 * Assert every interactive element matched by `selector` has a touch target
 * of at least `minPx` on its smaller dimension. WCAG 2.5.5 / Apple HIG → 44px.
 * Skips elements that are not visible (0x0).
 */
export async function assertTouchTargets(page: Page, selector: string, minPx = 44): Promise<void> {
  const sizes = await page.locator(selector).evaluateAll((els) =>
    els
      .filter((el) => {
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0; // visible only
      })
      .map((el) => {
        const r = el.getBoundingClientRect();
        return {
          w: Math.round(r.width),
          h: Math.round(r.height),
          text: (el.textContent ?? '').trim().slice(0, 24),
        };
      }),
  );
  for (const s of sizes) {
    expect(
      Math.min(s.w, s.h),
      `touch target too small for "${selector}" ("${s.text}"): ${s.w}x${s.h} < ${minPx}px`,
    ).toBeGreaterThanOrEqual(minPx);
  }
}

/** Capture a full-page screenshot for human before/after review. */
export async function shot(page: Page, surface: string, width: number): Promise<void> {
  await page.screenshot({
    path: `test-results/responsive/${surface}-${width}.png`,
    fullPage: true,
  });
}
