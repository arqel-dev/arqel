import type { ActionSchema } from '@arqel-dev/types/actions';
import type { FieldSchema } from '@arqel-dev/types/fields';
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

const baseField = {
  required: false,
  readonly: false,
  disabled: false,
  placeholder: null,
  helperText: null,
  defaultValue: null,
  columnSpan: 1,
  live: false,
  liveDebounce: null,
  validation: { rules: [], messages: {}, attribute: null },
  visibility: { create: true, edit: true, detail: true, table: true, canSee: true },
  dependsOn: [],
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

  it('renders form-field labels and select options from action.formFields (#213)', async () => {
    const user = userEvent.setup();
    const onInvoke = vi.fn();
    // Rich payload serialised server-side from Action::form() (#213).
    const reasonField: FieldSchema = {
      ...baseField,
      type: 'select',
      name: 'reason',
      label: 'Reason',
      component: 'SelectInput',
      required: true,
      props: { options: { a: 'Alpha', b: 'Beta' } },
    };
    const noteField: FieldSchema = {
      ...baseField,
      type: 'text',
      name: 'note',
      label: 'Note',
      component: 'TextInput',
      props: {},
    };
    const formAction: ActionSchema = {
      ...baseAction,
      name: 'transfer',
      label: 'Transfer',
      method: 'POST',
      form: [
        { name: 'reason', type: 'select' },
        { name: 'note', type: 'text' },
      ],
      formFields: [reasonField, noteField],
    };

    render(<ActionButton action={formAction} onInvoke={onInvoke} />);
    await user.click(screen.getByRole('button', { name: 'Transfer' }));

    // The modal must render real inputs — labels + the select's options,
    // not an empty modal (the pre-#213 bug).
    expect(await screen.findByText('Reason')).toBeInTheDocument();
    expect(screen.getByText('Note')).toBeInTheDocument();
    expect(screen.getByText('Alpha')).toBeInTheDocument();
    expect(screen.getByText('Beta')).toBeInTheDocument();
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
