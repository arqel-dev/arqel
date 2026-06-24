import { describe, expect, it } from 'vitest';
import {
  formatCompact,
  formatCurrency,
  formatDate,
  formatDecimal,
  formatPlural,
  toBcp47,
} from '../lib/format';

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

describe('formatDecimal', () => {
  it('formats with a dot decimal separator in en', () => {
    expect(formatDecimal(4.5, 'en', 1)).toBe('4.5');
  });

  it('formats with a comma decimal separator in pt-BR', () => {
    expect(formatDecimal(4.5, 'pt-BR', 1)).toBe('4,5');
  });

  it('pads to the requested fraction digits', () => {
    expect(formatDecimal(4, 'en', 1)).toBe('4.0');
  });
});

describe('formatPlural', () => {
  const stars = { one: 'estrela', other: 'estrelas' };

  it('selects the singular form for a count of 1 in pt-BR', () => {
    expect(formatPlural(1, 'pt-BR', stars)).toBe('1 estrela');
    expect(formatPlural(1, 'pt-BR', { one: 'útil', other: 'úteis' })).toBe('1 útil');
  });

  it('selects the plural form for counts in the other category in pt-BR', () => {
    expect(formatPlural(2, 'pt-BR', stars)).toBe('2 estrelas');
    expect(formatPlural(5, 'pt-BR', { one: 'útil', other: 'úteis' })).toBe('5 úteis');
  });

  it('follows CLDR for 0, which is the one category in pt-BR but other in en', () => {
    expect(formatPlural(0, 'pt-BR', stars)).toBe('0 estrela');
    expect(formatPlural(0, 'en', { one: 'item', other: 'items' })).toBe('0 items');
  });

  it('applies locale-aware thousands grouping to the count', () => {
    expect(formatPlural(1234, 'en', { one: 'item', other: 'items' })).toBe('1,234 items');
    expect(formatPlural(1234, 'pt-BR', stars)).toBe('1.234 estrelas');
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
