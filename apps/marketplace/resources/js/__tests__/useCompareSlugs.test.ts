import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { useCompareSlugs } from '../hooks/useCompareSlugs';

const KEY = 'arqel:compare:slugs';

describe('useCompareSlugs', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  afterEach(() => {
    window.localStorage.clear();
  });

  it('adds and removes slugs', () => {
    const { result } = renderHook(() => useCompareSlugs());
    act(() => result.current.addSlug('foo'));
    expect(result.current.slugs).toEqual(['foo']);
    act(() => result.current.addSlug('bar'));
    expect(result.current.slugs).toEqual(['foo', 'bar']);
    act(() => result.current.removeSlug('foo'));
    expect(result.current.slugs).toEqual(['bar']);
  });

  it('caps at 3 slugs', () => {
    const { result } = renderHook(() => useCompareSlugs());
    act(() => {
      result.current.addSlug('a');
      result.current.addSlug('b');
      result.current.addSlug('c');
    });
    act(() => result.current.addSlug('d'));
    expect(result.current.slugs).toEqual(['a', 'b', 'c']);
    expect(result.current.isFull).toBe(true);
  });

  it('persists changes to localStorage', () => {
    const { result } = renderHook(() => useCompareSlugs());
    act(() => result.current.addSlug('foo'));
    expect(window.localStorage.getItem(KEY)).toContain('foo');
    act(() => result.current.clear());
    expect(window.localStorage.getItem(KEY)).toBe('[]');
  });

  it('reads existing slugs from localStorage on mount and is SSR-safe to call', () => {
    window.localStorage.setItem(KEY, JSON.stringify(['x', 'y']));
    const { result } = renderHook(() => useCompareSlugs());
    expect(result.current.slugs).toEqual(['x', 'y']);
    // SSR-safe smoke: invalid payload returns empty
    window.localStorage.setItem(KEY, '{not-json');
    const { result: r2 } = renderHook(() => useCompareSlugs());
    expect(r2.current.slugs).toEqual([]);
  });
});
