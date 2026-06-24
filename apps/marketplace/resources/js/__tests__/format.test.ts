import { describe, expect, it } from 'vitest';
import { formatCompact, formatCurrency, formatDate, toBcp47 } from '../lib/format';

describe('toBcp47', () => {
  it('maps underscore locales to BCP-47 tags', () => {
    expect(toBcp47('pt_BR')).toBe('pt-BR');
    expect(toBcp47('en')).toBe('en');
  });
});

describe('formatCurrency', () => {
  it('formats cents as USD in en', () => {
    expect(formatCurrency(2500, 'USD', 'en')).toBe('$25.00');
  });

  it('formats cents as BRL with comma decimal and grouping in pt-BR', () => {
    const out = formatCurrency(199900, 'BRL', 'pt-BR');
    expect(out).toContain('R$');
    expect(out).toContain('1.999,00');
  });
});

describe('formatCompact', () => {
  it('uses English compact suffixes in en', () => {
    expect(formatCompact(12345, 'en')).toBe('12K');
  });

  it('uses localized compact words in pt-BR', () => {
    // Intl separates the value and the "mil" unit with a narrow no-break space.
    expect(formatCompact(12345, 'pt-BR')).toMatch(/^12\s*mil$/);
  });
});

describe('formatDate', () => {
  it('formats an ISO date for the active locale', () => {
    expect(formatDate('2026-06-23T14:05:00Z', 'pt-BR')).toBe('23/06/2026');
  });

  it('returns a dash for empty values', () => {
    expect(formatDate(null, 'en')).toBe('—');
    expect(formatDate('', 'en')).toBe('—');
  });

  it('returns the raw value when it is not a parseable date', () => {
    expect(formatDate('not-a-date', 'en')).toBe('not-a-date');
  });
});
