/**
 * Vitest + Testing Library coverage for `<RepeaterInput>`.
 *
 * The schema/props plumbing is exercised by composing a synthetic
 * `FieldSchema` shape per test rather than relying on a (yet to be
 * shipped) `RepeaterFieldSchema` discriminant from `@arqel/types`.
 */

import type { FieldSchema } from '@arqel/types/fields';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { RepeaterInput } from './RepeaterInput.js';

// Capture the latest `onDragEnd` handler and `items[]` passed to
// <DndContext> / <SortableContext> so the test can simulate a drag-drop
// reorder without relying on jsdom's (incomplete) pointer/keyboard event
// support for dnd-kit's sensors.
type DndEndHandler = (e: { active: { id: string }; over: { id: string } | null }) => void;
const capturedDnd: {
  onDragEnd: DndEndHandler | null;
  items: ReadonlyArray<string> | null;
} = { onDragEnd: null, items: null };

vi.mock('@dnd-kit/core', async () => {
  const actual = await vi.importActual<typeof import('@dnd-kit/core')>('@dnd-kit/core');
  return {
    ...actual,
    DndContext: ({
      children,
      onDragEnd,
    }: {
      children: React.ReactNode;
      onDragEnd: DndEndHandler;
    }) => {
      capturedDnd.onDragEnd = onDragEnd;
      return <>{children}</>;
    },
  };
});

vi.mock('@dnd-kit/sortable', async () => {
  const actual = await vi.importActual<typeof import('@dnd-kit/sortable')>('@dnd-kit/sortable');
  return {
    ...actual,
    SortableContext: ({
      children,
      items,
    }: {
      children: React.ReactNode;
      items: ReadonlyArray<string>;
    }) => {
      capturedDnd.items = items;
      return <>{children}</>;
    },
  };
});

interface SubField {
  name: string;
  type: string;
  label?: string;
  options?: ReadonlyArray<{ value: string | number; label: string }> | Record<string, string>;
}

interface RepProps {
  schema?: SubField[];
  minItems?: number | null;
  maxItems?: number | null;
  reorderable?: boolean;
  collapsible?: boolean;
  cloneable?: boolean;
  itemLabel?: string | null;
}

function buildField(overrides: RepProps = {}): FieldSchema {
  const props = {
    schema: overrides.schema ?? [{ name: 'name', type: 'text', label: 'Name' }],
    minItems: overrides.minItems ?? null,
    maxItems: overrides.maxItems ?? null,
    reorderable: overrides.reorderable ?? true,
    collapsible: overrides.collapsible ?? false,
    cloneable: overrides.cloneable ?? true,
    itemLabel: overrides.itemLabel ?? null,
  };

  return {
    type: 'repeater',
    name: 'addresses',
    label: 'Addresses',
    component: 'RepeaterInput',
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
    props,
  } as unknown as FieldSchema;
}

describe('<RepeaterInput>', () => {
  it('renders the legend and an empty list with an add button', () => {
    const onChange = vi.fn();
    render(<RepeaterInput field={buildField()} value={[]} onChange={onChange} />);

    expect(screen.getByText('Addresses')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Add item' })).toBeInTheDocument();
    expect(screen.queryByRole('article')).toBeNull();
  });

  it('adds a new item when the add button is clicked', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<RepeaterInput field={buildField()} value={[]} onChange={onChange} />);

    await user.click(screen.getByRole('button', { name: 'Add item' }));

    expect(screen.getAllByRole('article')).toHaveLength(1);
    expect(onChange).toHaveBeenLastCalledWith([{ name: '' }]);
  });

  it('updates an item when its sub-field value changes', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<RepeaterInput field={buildField()} value={[{ name: 'Home' }]} onChange={onChange} />);

    const input = screen.getByLabelText('Name') as HTMLInputElement;
    expect(input).toHaveValue('Home');

    await user.clear(input);
    await user.type(input, 'Work');

    expect(onChange).toHaveBeenLastCalledWith([{ name: 'Work' }]);
  });

  it('moves an item up when the move-up button is clicked', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <RepeaterInput
        field={buildField()}
        value={[{ name: 'A' }, { name: 'B' }]}
        onChange={onChange}
      />,
    );

    const upButtons = screen.getAllByRole('button', { name: 'Move up' });
    // Click the up button on the second item.
    await user.click(upButtons[1] as HTMLButtonElement);

    expect(onChange).toHaveBeenLastCalledWith([{ name: 'B' }, { name: 'A' }]);
  });

  it('moves an item down when the move-down button is clicked', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <RepeaterInput
        field={buildField()}
        value={[{ name: 'A' }, { name: 'B' }]}
        onChange={onChange}
      />,
    );

    const downButtons = screen.getAllByRole('button', { name: 'Move down' });
    await user.click(downButtons[0] as HTMLButtonElement);

    expect(onChange).toHaveBeenLastCalledWith([{ name: 'B' }, { name: 'A' }]);
  });

  it('disables move-up on the first item and move-down on the last item', () => {
    const onChange = vi.fn();
    render(
      <RepeaterInput
        field={buildField()}
        value={[{ name: 'A' }, { name: 'B' }]}
        onChange={onChange}
      />,
    );

    const upButtons = screen.getAllByRole('button', { name: 'Move up' });
    const downButtons = screen.getAllByRole('button', { name: 'Move down' });

    expect(upButtons[0]).toBeDisabled();
    expect(upButtons[1]).not.toBeDisabled();
    expect(downButtons[0]).not.toBeDisabled();
    expect(downButtons[1]).toBeDisabled();
  });

  it('removes an item when the remove button is clicked', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <RepeaterInput
        field={buildField()}
        value={[{ name: 'A' }, { name: 'B' }]}
        onChange={onChange}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Remove item 1' }));

    expect(onChange).toHaveBeenLastCalledWith([{ name: 'B' }]);
  });

  it('disables the add button when items.length reaches maxItems', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <RepeaterInput
        field={buildField({ maxItems: 2 })}
        value={[{ name: 'A' }]}
        onChange={onChange}
      />,
    );

    const addButton = screen.getByRole('button', { name: 'Add item' });
    expect(addButton).not.toBeDisabled();

    await user.click(addButton);

    expect(screen.getByRole('button', { name: 'Add item' })).toBeDisabled();
  });

  it('disables the remove button when items.length equals minItems', () => {
    const onChange = vi.fn();
    render(
      <RepeaterInput
        field={buildField({ minItems: 1 })}
        value={[{ name: 'A' }]}
        onChange={onChange}
      />,
    );

    expect(screen.getByRole('button', { name: 'Remove item 1' })).toBeDisabled();
  });

  it('clones an item when the clone button is clicked', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<RepeaterInput field={buildField()} value={[{ name: 'A' }]} onChange={onChange} />);

    await user.click(screen.getByRole('button', { name: 'Clone item 1' }));

    expect(onChange).toHaveBeenLastCalledWith([{ name: 'A' }, { name: 'A' }]);
    expect(screen.getAllByRole('article')).toHaveLength(2);
  });

  it('resolves {{key}} placeholders in the itemLabel template', () => {
    const onChange = vi.fn();
    render(
      <RepeaterInput
        field={buildField({ itemLabel: 'Address {{name}}' })}
        value={[{ name: 'Home' }, { name: 'Work' }]}
        onChange={onChange}
      />,
    );

    expect(screen.getByText('Address Home')).toBeInTheDocument();
    expect(screen.getByText('Address Work')).toBeInTheDocument();
  });

  it('toggles the collapsed state when collapsible=true', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <RepeaterInput
        field={buildField({ collapsible: true })}
        value={[{ name: 'A' }]}
        onChange={onChange}
      />,
    );

    expect(screen.getByLabelText('Name')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Collapse item 1' }));

    expect(screen.queryByLabelText('Name')).toBeNull();
    expect(screen.getByRole('button', { name: 'Expand item 1' })).toBeInTheDocument();
  });

  it('renders a drag handle for each item when reorderable=true', () => {
    const onChange = vi.fn();
    render(
      <RepeaterInput
        field={buildField()}
        value={[{ name: 'A' }, { name: 'B' }]}
        onChange={onChange}
      />,
    );

    expect(screen.getByLabelText('Drag to reorder item 1')).toBeInTheDocument();
    expect(screen.getByLabelText('Drag to reorder item 2')).toBeInTheDocument();
  });

  it('hides the drag handle when reorderable=false', () => {
    const onChange = vi.fn();
    render(
      <RepeaterInput
        field={buildField({ reorderable: false })}
        value={[{ name: 'A' }, { name: 'B' }]}
        onChange={onChange}
      />,
    );

    expect(screen.queryByLabelText('Drag to reorder item 1')).toBeNull();
    expect(screen.queryByLabelText('Drag to reorder item 2')).toBeNull();
    // up/down buttons are also hidden when reorderable=false (current behavior).
    expect(screen.queryByRole('button', { name: 'Move up' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Move down' })).toBeNull();
  });

  it('reorders items when dnd-kit fires onDragEnd', () => {
    const onChange = vi.fn();
    render(
      <RepeaterInput
        field={buildField()}
        value={[{ name: 'A' }, { name: 'B' }, { name: 'C' }]}
        onChange={onChange}
      />,
    );

    const ids = capturedDnd.items;
    const onDragEnd = capturedDnd.onDragEnd;
    if (!ids || !onDragEnd) throw new Error('dnd-kit not captured');
    expect(ids).toHaveLength(3);

    // Simulate dragging the first item over the last item: dnd-kit's
    // PointerSensor or KeyboardSensor would emit this same event shape
    // (active = item being dragged, over = drop target).
    onDragEnd({
      active: { id: ids[0] as string },
      over: { id: ids[2] as string },
    });

    expect(onChange).toHaveBeenLastCalledWith([{ name: 'B' }, { name: 'C' }, { name: 'A' }]);
  });

  it('renders a select sub-field with the schema-supplied options', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <RepeaterInput
        field={buildField({
          schema: [
            {
              name: 'kind',
              type: 'select',
              label: 'Kind',
              options: [
                { value: 'home', label: 'Home' },
                { value: 'work', label: 'Work' },
              ],
            },
          ],
        })}
        value={[{ kind: 'home' }]}
        onChange={onChange}
      />,
    );

    const select = screen.getByLabelText('Kind') as HTMLSelectElement;
    expect(select).toHaveValue('home');

    await user.selectOptions(select, 'work');

    expect(onChange).toHaveBeenLastCalledWith([{ kind: 'work' }]);
  });
});
