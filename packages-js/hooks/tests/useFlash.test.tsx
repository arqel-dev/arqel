import { renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { useFlash } from '../src/useFlash.js';
import { resetMockPage, setMockPage } from './setup.js';

afterEach(() => {
  resetMockPage();
});

describe('useFlash', () => {
  it('returns the current flash payload from shared props', () => {
    setMockPage({
      props: {
        flash: { success: 'Saved.', error: null, info: null, warning: null },
      },
    });

    const { result } = renderHook(() => useFlash());
    expect(result.current.success).toBe('Saved.');
    expect(result.current.error).toBeNull();
  });

  it('falls back to an empty payload when the page has no `flash`', () => {
    setMockPage({ props: {} });

    const { result } = renderHook(() => useFlash());
    expect(result.current).toEqual({
      success: null,
      error: null,
      info: null,
      warning: null,
    });
  });

  it('fires onMessage once per new value of each kind', () => {
    setMockPage({
      props: { flash: { success: 'Hello', error: null, info: null, warning: null } },
    });

    const onMessage = vi.fn();
    const { rerender } = renderHook(() => useFlash({ onMessage }));

    expect(onMessage).toHaveBeenCalledTimes(1);
    expect(onMessage).toHaveBeenCalledWith('success', 'Hello');

    // Same message, same render → no second fire.
    rerender();
    expect(onMessage).toHaveBeenCalledTimes(1);

    // New value of the same kind → fires again.
    setMockPage({
      props: { flash: { success: 'World', error: null, info: null, warning: null } },
    });
    rerender();
    expect(onMessage).toHaveBeenCalledTimes(2);
    expect(onMessage).toHaveBeenLastCalledWith('success', 'World');
  });

  it('fires onMessage for multiple kinds in the same payload', () => {
    setMockPage({
      props: {
        flash: { success: 'Done', error: 'Oops', info: null, warning: 'Heads up' },
      },
    });

    const onMessage = vi.fn();
    renderHook(() => useFlash({ onMessage }));

    expect(onMessage).toHaveBeenCalledTimes(3);
    expect(onMessage).toHaveBeenCalledWith('success', 'Done');
    expect(onMessage).toHaveBeenCalledWith('error', 'Oops');
    expect(onMessage).toHaveBeenCalledWith('warning', 'Heads up');
  });
});
