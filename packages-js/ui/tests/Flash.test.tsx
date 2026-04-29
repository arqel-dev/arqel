import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { FlashToast } from '../src/flash/FlashToast.js';

describe('FlashToast', () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  it('renders message and uses role=alert for errors', () => {
    render(<FlashToast kind="error" message="Boom" onDismiss={() => {}} />);
    const toast = screen.getByRole('alert');
    expect(toast).toHaveTextContent('Boom');
  });

  it('uses role=status for non-error kinds', () => {
    render(<FlashToast kind="success" message="Saved" onDismiss={() => {}} />);
    expect(screen.getByRole('status')).toHaveTextContent('Saved');
  });

  it('dismisses after durationMs', () => {
    const onDismiss = vi.fn();
    render(<FlashToast kind="info" message="hi" onDismiss={onDismiss} durationMs={1000} />);
    expect(onDismiss).not.toHaveBeenCalled();
    act(() => {
      vi.advanceTimersByTime(1000);
    });
    expect(onDismiss).toHaveBeenCalled();
  });

  it('does not auto-dismiss when durationMs is 0', () => {
    const onDismiss = vi.fn();
    render(<FlashToast kind="info" message="hi" onDismiss={onDismiss} durationMs={0} />);
    act(() => {
      vi.advanceTimersByTime(60_000);
    });
    expect(onDismiss).not.toHaveBeenCalled();
  });

  it('manual dismiss button fires onDismiss', async () => {
    vi.useRealTimers();
    const user = userEvent.setup();
    const onDismiss = vi.fn();
    render(<FlashToast kind="info" message="hi" onDismiss={onDismiss} durationMs={0} />);
    await user.click(screen.getByRole('button', { name: 'Dismiss' }));
    expect(onDismiss).toHaveBeenCalled();
  });
});
