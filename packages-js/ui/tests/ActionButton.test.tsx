import type { ActionSchema } from '@arqel/types/actions';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { ActionButton } from '../src/action/ActionButton.js';

const baseAction: ActionSchema = {
  name: 'delete',
  type: 'row',
  label: 'Delete',
  color: 'destructive',
  variant: 'destructive',
  method: 'DELETE',
};

describe('ActionButton', () => {
  it('invokes immediately when no confirmation or form', async () => {
    const user = userEvent.setup();
    const onInvoke = vi.fn();
    render(<ActionButton action={{ ...baseAction, color: 'primary' }} onInvoke={onInvoke} />);
    await user.click(screen.getByRole('button', { name: 'Delete' }));
    expect(onInvoke).toHaveBeenCalledTimes(1);
  });

  it('opens ConfirmDialog when requiresConfirmation', async () => {
    const user = userEvent.setup();
    const onInvoke = vi.fn();
    render(
      <ActionButton
        action={{ ...baseAction, requiresConfirmation: true, confirmation: { heading: 'Sure?' } }}
        onInvoke={onInvoke}
      />,
    );
    await user.click(screen.getByRole('button', { name: 'Delete' }));
    expect(screen.getByText('Sure?')).toBeInTheDocument();
    expect(onInvoke).not.toHaveBeenCalled();
  });

  it('disables submit until typed text matches requiresText', async () => {
    const user = userEvent.setup();
    const onInvoke = vi.fn();
    render(
      <ActionButton
        action={{
          ...baseAction,
          requiresConfirmation: true,
          confirmation: {
            heading: 'Type DELETE',
            requiresText: 'DELETE',
            submitLabel: 'Delete it',
          },
        }}
        onInvoke={onInvoke}
      />,
    );
    await user.click(screen.getByRole('button', { name: 'Delete' }));
    const submit = screen.getByRole('button', { name: 'Delete it' });
    expect(submit).toBeDisabled();

    const input = screen.getByRole('textbox');
    await user.type(input, 'DELETE');
    expect(submit).not.toBeDisabled();

    await user.click(submit);
    expect(onInvoke).toHaveBeenCalledTimes(1);
  });

  it('respects schema-level disabled state', async () => {
    const user = userEvent.setup();
    const onInvoke = vi.fn();
    render(<ActionButton action={{ ...baseAction, disabled: true }} onInvoke={onInvoke} />);
    const btn = screen.getByRole('button', { name: 'Delete' });
    expect(btn).toBeDisabled();
    await user.click(btn);
    expect(onInvoke).not.toHaveBeenCalled();
  });
});
