import { renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { reload } = vi.hoisted(() => ({ reload: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  router: {
    reload,
  },
}));

import { useFallbackPolling } from './useFallbackPolling';

describe('useFallbackPolling', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    reload.mockClear();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('chama router.reload no intervalo quando enabled=true', () => {
    renderHook(() => useFallbackPolling({ enabled: true, intervalMs: 1000, only: ['records'] }));

    expect(reload).not.toHaveBeenCalled();

    vi.advanceTimersByTime(1000);
    expect(reload).toHaveBeenCalledTimes(1);
    expect(reload).toHaveBeenLastCalledWith({ only: ['records'] });

    vi.advanceTimersByTime(2000);
    expect(reload).toHaveBeenCalledTimes(3);
  });

  it('não chama reload quando enabled=false', () => {
    renderHook(() => useFallbackPolling({ enabled: false, intervalMs: 1000 }));
    vi.advanceTimersByTime(5000);
    expect(reload).not.toHaveBeenCalled();
  });

  it('limpa o interval no unmount', () => {
    const { unmount } = renderHook(() => useFallbackPolling({ enabled: true, intervalMs: 1000 }));

    vi.advanceTimersByTime(1000);
    expect(reload).toHaveBeenCalledTimes(1);

    unmount();
    vi.advanceTimersByTime(5000);
    expect(reload).toHaveBeenCalledTimes(1);
  });

  it('reage a mudanças no flag enabled', () => {
    const { rerender } = renderHook(
      ({ enabled }: { enabled: boolean }) => useFallbackPolling({ enabled, intervalMs: 1000 }),
      { initialProps: { enabled: false } },
    );

    vi.advanceTimersByTime(2000);
    expect(reload).not.toHaveBeenCalled();

    rerender({ enabled: true });
    vi.advanceTimersByTime(1000);
    expect(reload).toHaveBeenCalledTimes(1);

    rerender({ enabled: false });
    vi.advanceTimersByTime(5000);
    expect(reload).toHaveBeenCalledTimes(1);
  });
});
