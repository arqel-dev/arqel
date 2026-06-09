import type { ActionSchema } from '@arqel-dev/types/actions';
import type { FieldSchema } from '@arqel-dev/types/fields';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { ActionMenu } from '../src/action/ActionMenu.js';

function makeAction(name: string, label: string): ActionSchema {
  return {
    name,
    type: 'row',
    label,
    color: 'primary',
    variant: 'default',
    method: 'POST',
  };
}

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

describe('ActionMenu', () => {
  it('renders inline buttons when count <= threshold', () => {
    const onInvoke = vi.fn();
    render(
      <ActionMenu
        actions={[makeAction('a', 'Edit'), makeAction('b', 'Restore')]}
        onInvoke={onInvoke}
      />,
    );
    expect(screen.getByRole('button', { name: 'Edit' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Restore' })).toBeInTheDocument();
    expect(screen.queryByLabelText('Actions')).toBeNull();
  });

  it('collapses into a dropdown trigger when count > threshold', () => {
    const onInvoke = vi.fn();
    render(
      <ActionMenu
        inlineThreshold={2}
        actions={[makeAction('a', 'Edit'), makeAction('b', 'Restore'), makeAction('c', 'Delete')]}
        onInvoke={onInvoke}
      />,
    );
    expect(screen.getByRole('button', { name: 'Actions' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Edit' })).toBeNull();
  });

  it('returns null when no actions', () => {
    const onInvoke = vi.fn();
    const { container } = render(<ActionMenu actions={[]} onInvoke={onInvoke} />);
    expect(container).toBeEmptyDOMElement();
  });

  // ── Regression: dropdown items must honour confirmation/form (#229, #231) ──

  it('opens ConfirmDialog (not direct invoke) for a dropdown requiresConfirmation action (#229)', async () => {
    const user = userEvent.setup();
    const onInvoke = vi.fn();
    const deleteAction: ActionSchema = {
      ...makeAction('delete', 'Delete'),
      method: 'DELETE',
      color: 'destructive',
      variant: 'destructive',
      requiresConfirmation: true,
      confirmation: { heading: 'Delete this row?' },
    };
    render(
      <ActionMenu
        inlineThreshold={3}
        actions={[
          makeAction('a', 'Edit'),
          makeAction('b', 'Restore'),
          makeAction('c', 'View'),
          deleteAction,
        ]}
        onInvoke={onInvoke}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Actions' }));
    await user.click(screen.getByRole('menuitem', { name: 'Delete' }));

    // The dialog must appear and onInvoke must NOT have fired yet.
    expect(await screen.findByText('Delete this row?')).toBeInTheDocument();
    expect(onInvoke).not.toHaveBeenCalled();

    // Confirming fires the invocation exactly once.
    await user.click(screen.getByRole('button', { name: 'Delete' }));
    expect(onInvoke).toHaveBeenCalledTimes(1);
    expect(onInvoke.mock.calls[0]?.[0]).toMatchObject({ name: 'delete' });
  });

  it('opens ActionFormModal for a dropdown action with a form (#231)', async () => {
    const user = userEvent.setup();
    const onInvoke = vi.fn();
    const reasonField: FieldSchema = {
      ...baseField,
      type: 'text',
      name: 'reason',
      label: 'Reason',
      component: 'TextInput',
      props: {},
    };
    const formAction: ActionSchema = {
      ...makeAction('transfer', 'Transfer'),
      form: [{ name: 'reason', type: 'text' }],
      formFields: [reasonField],
    };
    render(
      <ActionMenu
        inlineThreshold={3}
        actions={[
          makeAction('a', 'Edit'),
          makeAction('b', 'Restore'),
          makeAction('c', 'View'),
          formAction,
        ]}
        onInvoke={onInvoke}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Actions' }));
    await user.click(screen.getByRole('menuitem', { name: 'Transfer' }));

    // The form modal must render the field — not invoke directly.
    expect(await screen.findByText('Reason')).toBeInTheDocument();
    expect(onInvoke).not.toHaveBeenCalled();
  });

  it('invokes directly for a plain dropdown action (no confirm/form regression)', async () => {
    const user = userEvent.setup();
    const onInvoke = vi.fn();
    render(
      <ActionMenu
        inlineThreshold={3}
        actions={[
          makeAction('a', 'Edit'),
          makeAction('b', 'Restore'),
          makeAction('c', 'View'),
          makeAction('d', 'Ping'),
        ]}
        onInvoke={onInvoke}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Actions' }));
    await user.click(screen.getByRole('menuitem', { name: 'Ping' }));

    expect(onInvoke).toHaveBeenCalledTimes(1);
    expect(onInvoke.mock.calls[0]?.[0]).toMatchObject({ name: 'd' });
  });
});
