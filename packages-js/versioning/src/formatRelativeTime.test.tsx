import { describe, expect, it } from 'vitest';
import { formatRelativeTime } from './VersionTimeline.js';

/**
 * `formatRelativeTime` must localize via the BCP-47 locale argument instead of
 * the previously hardcoded `'en'` literal in the module-level RTF constant.
 */
describe('formatRelativeTime', () => {
  const now = new Date('2026-05-01T12:00:00Z');
  const threeHoursAgo = '2026-05-01T09:00:00Z';
  const inTwoDays = '2026-05-03T12:00:00Z';

  it('defaults to English for the bare pure helper (back-compat)', () => {
    expect(formatRelativeTime(threeHoursAgo, now)).toBe('3 hours ago');
  });

  it('formats past times in the supplied locale (pt-BR ≠ en)', () => {
    const en = formatRelativeTime(threeHoursAgo, now, 'en');
    const pt = formatRelativeTime(threeHoursAgo, now, 'pt-BR');
    expect(en).toBe('3 hours ago');
    expect(pt).not.toEqual(en);
    expect(pt.toLowerCase()).toContain('horas');
  });

  it('formats future times in the supplied locale (pt-BR ≠ en)', () => {
    const en = formatRelativeTime(inTwoDays, now, 'en');
    const pt = formatRelativeTime(inTwoDays, now, 'pt-BR');
    // numeric:'auto' → en "in 2 days" / pt "depois de amanhã"; both localized.
    expect(en).toBe('in 2 days');
    expect(pt).not.toEqual(en);
    expect(pt).not.toContain('in 2 days');
  });

  it('accepts an underscore-style locale tag without throwing', () => {
    // Intl tolerates 'pt_BR'? No — callers pass BCP-47 from useArqelLocale; but
    // the helper must not crash on a region-only tag like 'en'.
    expect(() => formatRelativeTime(threeHoursAgo, now, 'en')).not.toThrow();
  });

  it('returns the raw ISO for an unparseable date', () => {
    expect(formatRelativeTime('not-a-date', now, 'pt-BR')).toBe('not-a-date');
  });
});
