import { renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { useNavigation } from '../src/useNavigation.js';
import { resetMockPage, setMockPage } from './setup.js';

afterEach(() => {
  resetMockPage();
});

describe('useNavigation', () => {
  it('returns an empty array when no `panel.navigation` is defined', () => {
    setMockPage({ props: {} });
    expect(renderHook(() => useNavigation()).result.current.items).toEqual([]);
  });

  it('returns the navigation tree when present', () => {
    setMockPage({
      props: {
        panel: {
          navigation: [
            { label: 'Posts', url: '/admin/posts', icon: 'document' },
            { label: 'Users', url: '/admin/users', badge: 3 },
          ],
        },
      },
    });

    const { result } = renderHook(() => useNavigation());
    expect(result.current.items).toHaveLength(2);
    expect(result.current.items[0]?.label).toBe('Posts');
    expect(result.current.items[1]?.badge).toBe(3);
  });

  it('coerces non-array `panel.navigation` to an empty list', () => {
    setMockPage({
      props: { panel: { navigation: 'not-an-array' as unknown as never } },
    });

    expect(renderHook(() => useNavigation()).result.current.items).toEqual([]);
  });
});
