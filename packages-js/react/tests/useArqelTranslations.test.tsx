import { renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

const { pageMock } = vi.hoisted(() => ({ pageMock: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  usePage: pageMock,
}));

import { useArqelTranslations } from '../src/utils/useArqelTranslations.js';

afterEach(() => {
  pageMock.mockReset();
});

describe('useArqelTranslations', () => {
  it('resolves keys against props.i18n.translations (the shell shares this)', () => {
    pageMock.mockReturnValue({
      props: {
        i18n: {
          locale: 'pt_BR',
          available: ['en', 'pt_BR'],
          translations: { table: { pagination: { previous: 'Anterior', next: 'Próximo' } } },
        },
      },
    });
    const { result } = renderHook(() => useArqelTranslations());
    expect(result.current('table.pagination.previous')).toBe('Anterior');
    expect(result.current('table.pagination.next')).toBe('Próximo');
  });

  it('returns the key when the translation is missing and no fallback given', () => {
    pageMock.mockReturnValue({
      props: { i18n: { locale: 'en', available: ['en'], translations: {} } },
    });
    const { result } = renderHook(() => useArqelTranslations());
    expect(result.current('table.pagination.previous')).toBe('table.pagination.previous');
  });

  it('returns the fallback when the translation is missing and one is given', () => {
    pageMock.mockReturnValue({
      props: { i18n: { locale: 'en', available: ['en'], translations: {} } },
    });
    const { result } = renderHook(() => useArqelTranslations());
    // a missing key with a human fallback renders the fallback, never the raw key.
    expect(result.current('table.pagination.previous', 'Prev')).toBe('Prev');
  });

  it('prefers the translation over the fallback when present', () => {
    pageMock.mockReturnValue({
      props: {
        i18n: {
          locale: 'pt_BR',
          available: ['pt_BR'],
          translations: { table: { pagination: { previous: 'Anterior' } } },
        },
      },
    });
    const { result } = renderHook(() => useArqelTranslations());
    expect(result.current('table.pagination.previous', 'Prev')).toBe('Anterior');
  });

  it('does not throw when the i18n prop is absent (non-Arqel page / tests)', () => {
    pageMock.mockReturnValue({ props: {} });
    const { result } = renderHook(() => useArqelTranslations());
    expect(result.current('anything.at.all')).toBe('anything.at.all');
  });

  it('returns a stable t reference across re-renders with the same i18n prop', () => {
    const props = {
      i18n: {
        locale: 'pt_BR',
        available: ['en', 'pt_BR'],
        translations: { table: { pagination: { previous: 'Anterior' } } },
      },
    };
    // usePage returns the same prop object identity each render (Inertia shares
    // a stable reference until a navigation), so the memoized dict — and thus t —
    // must keep the same identity instead of being rebuilt every render.
    pageMock.mockReturnValue({ props });

    const { result, rerender } = renderHook(() => useArqelTranslations());
    const first = result.current;
    rerender();
    rerender();
    expect(result.current).toBe(first);
  });

  it('applies :placeholder replacements', () => {
    pageMock.mockReturnValue({
      props: {
        i18n: {
          locale: 'en',
          available: ['en'],
          translations: { table: { pagination: { showing: 'Showing :from to :to of :total' } } },
        },
      },
    });
    const { result } = renderHook(() => useArqelTranslations());
    expect(
      result.current('table.pagination.showing', undefined, { from: 1, to: 10, total: 47 }),
    ).toBe('Showing 1 to 10 of 47');
  });
});
