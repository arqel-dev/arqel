import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { FormActions } from '../src/form/FormActions.js';

describe('FormActions', () => {
  it('renders submit button with default label', () => {
    render(<FormActions />);
    expect(screen.getByRole('button', { name: 'Save' })).toHaveAttribute('type', 'submit');
  });

  it('shows processing label and disables submit when processing', () => {
    render(<FormActions processing />);
    const submit = screen.getByRole('button', { name: 'Saving…' });
    expect(submit).toBeDisabled();
  });

  it('renders cancel and fires onCancel', async () => {
    const user = userEvent.setup();
    const onCancel = vi.fn();
    render(<FormActions onCancel={onCancel} />);
    await user.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(onCancel).toHaveBeenCalled();
  });
});
