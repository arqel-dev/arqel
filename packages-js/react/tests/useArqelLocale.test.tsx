import { renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

const { pageMock } = vi.hoisted(() => ({ pageMock: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  usePage: pageMock,
}));

import { toBcp47, useArqelLocale } from '../src/utils/useArqelLocale.js';

afterEach(() => {
  pageMock.mockReset();
});

describe('toBcp47', () => {
  it('maps underscore locale to a BCP-47 hyphen tag', () => {
    expect(toBcp47('pt_BR')).toBe('pt-BR');
  });

  it('passes hyphen tags through and trims', () => {
    expect(toBcp47(' en-US ')).toBe('en-US');
  });

  it('returns undefined for empty/nullish input', () => {
    expect(toBcp47('')).toBeUndefined();
    expect(toBcp47(null)).toBeUndefined();
    expect(toBcp47(undefined)).toBeUndefined();
  });
});

describe('useArqelLocale', () => {
  it('reads props.i18n.locale and maps it to BCP-47', () => {
    pageMock.mockReturnValue({ props: { i18n: { locale: 'pt_BR' } } });
    const { result } = renderHook(() => useArqelLocale());
    expect(result.current).toBe('pt-BR');
  });

  it('falls back to navigator.language (then en) when the i18n prop is absent', () => {
    pageMock.mockReturnValue({ props: {} });
    const { result } = renderHook(() => useArqelLocale());
    // jsdom reports navigator.language = 'en-US'; without a navigator it is 'en'.
    expect(result.current).toMatch(/^en(-[A-Z]{2})?$/);
  });
});
