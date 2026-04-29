import { renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { useResource } from '../src/useResource.js';
import { resetMockPage, setMockPage } from './setup.js';

interface Post {
  id: number;
  title: string;
}

afterEach(() => {
  resetMockPage();
});

describe('useResource', () => {
  it('returns an "empty" shape when the page has no Resource payload', () => {
    setMockPage({ props: {} });

    const { result } = renderHook(() => useResource());
    expect(result.current.resource).toBeNull();
    expect(result.current.records).toBeNull();
    expect(result.current.record).toBeNull();
    expect(result.current.filters).toEqual({});
  });

  it('reads `records` for index pages and exposes the typed list', () => {
    setMockPage({
      props: {
        resource: { slug: 'posts', label: 'Post', pluralLabel: 'Posts' },
        records: [
          { id: 1, title: 'A' },
          { id: 2, title: 'B' },
        ],
      },
    });

    const { result } = renderHook(() => useResource<Post>());
    expect(result.current.records).toEqual([
      { id: 1, title: 'A' },
      { id: 2, title: 'B' },
    ]);
    expect(result.current.record).toBeNull();
  });

  it('reads `record` for detail/edit pages', () => {
    setMockPage({
      props: {
        resource: { slug: 'posts', label: 'Post', pluralLabel: 'Posts' },
        record: { id: 42, title: 'Hello' },
      },
    });

    const { result } = renderHook(() => useResource<Post>());
    expect(result.current.record).toEqual({ id: 42, title: 'Hello' });
    expect(result.current.records).toBeNull();
  });

  it('returns server filters as-is', () => {
    setMockPage({
      props: { filters: { status: 'published', tier: 'pro' } },
    });

    expect(renderHook(() => useResource()).result.current.filters).toEqual({
      status: 'published',
      tier: 'pro',
    });
  });

  it('keeps raw page props accessible for escape hatch', () => {
    setMockPage({
      props: { extra: 'value', resource: { slug: 'x', label: 'X', pluralLabel: 'Xs' } },
    });

    const { result } = renderHook(() => useResource());
    expect(result.current.props['extra']).toBe('value');
  });
});
