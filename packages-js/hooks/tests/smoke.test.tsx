import { act, renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { useBreakpoint, useTable } from '../src/index.js';

describe('useTable', () => {
  it('toggles selection', () => {
    const { result } = renderHook(() => useTable());

    expect(result.current.selectedIds).toEqual([]);

    act(() => result.current.toggleSelection(1));
    expect(result.current.selectedIds).toEqual([1]);

    act(() => result.current.toggleSelection(1));
    expect(result.current.selectedIds).toEqual([]);
  });

  it('sets and clears filters', () => {
    const { result } = renderHook(() => useTable());

    act(() => result.current.setFilter('status', 'active'));
    expect(result.current.filters).toEqual({ status: 'active' });

    act(() => result.current.setFilter('status', ''));
    expect(result.current.filters).toEqual({});
  });

  it('seeds from defaults', () => {
    const { result } = renderHook(() =>
      useTable({
        defaultSort: { column: 'name', direction: 'asc' },
        defaultFilters: { active: true },
        defaultSelection: ['a'],
      }),
    );

    expect(result.current.sort).toEqual({ column: 'name', direction: 'asc' });
    expect(result.current.filters).toEqual({ active: true });
    expect(result.current.selectedIds).toEqual(['a']);
  });
});

describe('useBreakpoint', () => {
  it('resolves to a Tailwind breakpoint', () => {
    const { result } = renderHook(() => useBreakpoint());
    expect(['sm', 'md', 'lg', 'xl', '2xl']).toContain(result.current);
  });
});
