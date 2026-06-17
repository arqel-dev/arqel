import type { ActionSchema } from '@arqel-dev/types/actions';
import type { FieldSchema } from '@arqel-dev/types/fields';
import { render, screen, within } from '@testing-library/react';
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
    // Under JSDOM (no CSS) both dual-render surfaces mount, so the trigger
    // query is ambiguous — scope it to the desktop dropdown subtree.
    const dropdown = within(document.querySelector('[data-arqel-action-dropdown]') as HTMLElement);
    expect(dropdown.getByRole('button', { name: 'Actions' })).toBeInTheDocument();
    expect(dropdown.queryByRole('button', { name: 'Edit' })).toBeNull();
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

    // Scope the (dual-render-ambiguous) trigger to the desktop dropdown; the
    // Radix menu content portals to document.body, so `menuitem` is unique
    // and stays on `screen`.
    const dropdown = within(document.querySelector('[data-arqel-action-dropdown]') as HTMLElement);
    await user.click(dropdown.getByRole('button', { name: 'Actions' }));
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

    const dropdown = within(document.querySelector('[data-arqel-action-dropdown]') as HTMLElement);
    await user.click(dropdown.getByRole('button', { name: 'Actions' }));
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

    const dropdown = within(document.querySelector('[data-arqel-action-dropdown]') as HTMLElement);
    await user.click(dropdown.getByRole('button', { name: 'Actions' }));
    await user.click(screen.getByRole('menuitem', { name: 'Ping' }));

    expect(onInvoke).toHaveBeenCalledTimes(1);
    expect(onInvoke.mock.calls[0]?.[0]).toMatchObject({ name: 'd' });
  });

  // ── Mobile bottom-sheet surface (responsive Phase 3) ──

  it('renders a bottom-sheet surface with full-width >=44px action items', async () => {
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

    // The mobile sheet trigger is the second "Actions" button (the sheet's
    // own bare <button>, distinct from the dropdown trigger). Open via it.
    const triggers = screen.getAllByRole('button', { name: 'Actions' });
    expect(triggers.length).toBeGreaterThanOrEqual(2); // dropdown + sheet co-exist under JSDOM
    await user.click(triggers[triggers.length - 1] as HTMLElement);

    // The sheet's action items carry the data hook and the min-h-11 class.
    const items = document.querySelectorAll('[data-arqel-sheet-action]');
    expect(items.length).toBe(4);
    items.forEach((el) => {
      expect(el.className).toContain('min-h-11');
    });

    // Selecting a plain action invokes once.
    await user.click(document.querySelector('[data-arqel-sheet-action]:last-child') as HTMLElement);
    expect(onInvoke).toHaveBeenCalledTimes(1);
    expect(onInvoke.mock.calls[0]?.[0]).toMatchObject({ name: 'd' });
  });
});
