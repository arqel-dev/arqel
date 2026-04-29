import { act, renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { useTable } from '../src/useTable.js';

describe('useTable — sort', () => {
  it('starts with null sort and applies the default direction "asc"', () => {
    const { result } = renderHook(() => useTable());
    expect(result.current.sort).toBeNull();

    act(() => result.current.setSort('name'));
    expect(result.current.sort).toEqual({ column: 'name', direction: 'asc' });
  });

  it('honours the explicit direction passed to setSort', () => {
    const { result } = renderHook(() => useTable());
    act(() => result.current.setSort('created_at', 'desc'));
    expect(result.current.sort).toEqual({ column: 'created_at', direction: 'desc' });
  });

  it('clearSort wipes the sort state', () => {
    const { result } = renderHook(() =>
      useTable({ defaultSort: { column: 'id', direction: 'asc' } }),
    );
    act(() => result.current.clearSort());
    expect(result.current.sort).toBeNull();
  });
});

describe('useTable — filters', () => {
  it('removes the entry when value is empty/null/undefined', () => {
    const { result } = renderHook(() => useTable());

    act(() => result.current.setFilter('status', 'active'));
    expect(result.current.filters).toEqual({ status: 'active' });

    act(() => result.current.setFilter('status', null));
    expect(result.current.filters).toEqual({});

    act(() => result.current.setFilter('status', 'active'));
    act(() => result.current.setFilter('status', undefined));
    expect(result.current.filters).toEqual({});
  });

  it('clearFilters resets the entire map', () => {
    const { result } = renderHook(() =>
      useTable({ defaultFilters: { status: 'active', tier: 'pro' } }),
    );

    act(() => result.current.clearFilters());
    expect(result.current.filters).toEqual({});
  });
});

describe('useTable — selection', () => {
  it('selectAll replaces the selection wholesale', () => {
    const { result } = renderHook(() => useTable());
    act(() => result.current.selectAll([1, 2, 3]));
    expect(result.current.selectedIds).toEqual([1, 2, 3]);

    act(() => result.current.selectAll(['a']));
    expect(result.current.selectedIds).toEqual(['a']);
  });

  it('isSelected reflects current selection', () => {
    const { result } = renderHook(() => useTable({ defaultSelection: [1, 2] }));
    expect(result.current.isSelected(1)).toBe(true);
    expect(result.current.isSelected(99)).toBe(false);
  });

  it('clearSelection wipes selectedIds', () => {
    const { result } = renderHook(() => useTable({ defaultSelection: [1, 2] }));
    act(() => result.current.clearSelection());
    expect(result.current.selectedIds).toEqual([]);
  });
});
