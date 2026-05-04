/**
 * Vitest + Testing Library coverage for `<BuilderInput>` (FIELDS-ADV-014
 * scoped — no dnd-kit, no Base UI Dialog).
 */

import type { FieldSchema } from '@arqel-dev/types/fields';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { BuilderInput } from './BuilderInput.js';

// See `RepeaterInput.test.tsx` for the rationale: capture the dnd-kit
// callbacks so the test can drive a drag-end without relying on jsdom's
// pointer/keyboard sensor support.
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

interface BlockEntry {
  type: string;
  label: string;
  icon?: string | null;
  schema: SubField[];
}

interface BProps {
  blocks?: Record<string, BlockEntry>;
  minItems?: number | null;
  maxItems?: number | null;
  reorderable?: boolean;
  collapsible?: boolean;
  cloneable?: boolean;
  itemLabel?: string | null;
}

const FIXTURE_BLOCKS: Record<string, BlockEntry> = {
  heading: {
    type: 'heading',
    label: 'Heading',
    icon: 'H',
    schema: [{ name: 'text', type: 'text', label: 'Text' }],
  },
  paragraph: {
    type: 'paragraph',
    label: 'Paragraph',
    icon: null,
    schema: [{ name: 'body', type: 'textarea', label: 'Body' }],
  },
  image: {
    type: 'image',
    label: 'Image',
    icon: null,
    schema: [
      { name: 'url', type: 'text', label: 'URL' },
      { name: 'alt', type: 'text', label: 'Alt' },
    ],
  },
};

function buildField(overrides: BProps = {}): FieldSchema {
  const props = {
    blocks: overrides.blocks ?? FIXTURE_BLOCKS,
    minItems: overrides.minItems ?? null,
    maxItems: overrides.maxItems ?? null,
    reorderable: overrides.reorderable ?? true,
    collapsible: overrides.collapsible ?? false,
    cloneable: overrides.cloneable ?? true,
    itemLabel: overrides.itemLabel ?? null,
  };

  return {
    type: 'builder',
    name: 'content',
    label: 'Content',
    component: 'BuilderInput',
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

describe('<BuilderInput>', () => {
  it('renders the legend and an empty list with an add button', () => {
    const onChange = vi.fn();
    render(<BuilderInput field={buildField()} value={[]} onChange={onChange} />);

    expect(screen.getByText('Content')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Add block' })).toBeInTheDocument();
    expect(screen.queryByRole('article')).toBeNull();
    expect(screen.queryByRole('menu')).toBeNull();
  });

  it('opens the block picker when add is clicked', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<BuilderInput field={buildField()} value={[]} onChange={onChange} />);

    await user.click(screen.getByRole('button', { name: 'Add block' }));

    expect(screen.getByRole('menu', { name: 'Add block' })).toBeInTheDocument();
  });

  it('shows all 3 fixture block types in the picker', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<BuilderInput field={buildField()} value={[]} onChange={onChange} />);

    await user.click(screen.getByRole('button', { name: 'Add block' }));

    const items = screen.getAllByRole('menuitem');
    expect(items).toHaveLength(3);
    expect(items.map((b) => b.textContent)).toEqual(
      expect.arrayContaining(['HHeading', 'Paragraph', 'Image']),
    );
  });

  it('adds a block of the chosen type when a menuitem is clicked', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<BuilderInput field={buildField()} value={[]} onChange={onChange} />);

    await user.click(screen.getByRole('button', { name: 'Add block' }));
    await user.click(screen.getByRole('menuitem', { name: /Paragraph/ }));

    expect(screen.getAllByRole('article')).toHaveLength(1);
    expect(onChange).toHaveBeenLastCalledWith([{ type: 'paragraph', data: { body: '' } }]);
  });

  it('renders the sub-field schema corresponding to the chosen block type', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<BuilderInput field={buildField()} value={[]} onChange={onChange} />);

    await user.click(screen.getByRole('button', { name: 'Add block' }));
    await user.click(screen.getByRole('menuitem', { name: /Image/ }));

    expect(screen.getByLabelText('URL')).toBeInTheDocument();
    expect(screen.getByLabelText('Alt')).toBeInTheDocument();
    expect(screen.queryByLabelText('Text')).toBeNull();
    expect(screen.queryByLabelText('Body')).toBeNull();
  });

  it('renders different sub-fields for blocks of different types', () => {
    const onChange = vi.fn();
    render(
      <BuilderInput
        field={buildField()}
        value={[
          { type: 'heading', data: { text: 'Hi' } },
          { type: 'paragraph', data: { body: 'Lorem' } },
        ]}
        onChange={onChange}
      />,
    );

    expect(screen.getByLabelText('Text')).toHaveValue('Hi');
    expect(screen.getByLabelText('Body')).toHaveValue('Lorem');
  });

  it('moves a block up when the move-up button is clicked', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <BuilderInput
        field={buildField()}
        value={[
          { type: 'heading', data: { text: 'A' } },
          { type: 'paragraph', data: { body: 'B' } },
        ]}
        onChange={onChange}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Move block 2 up' }));

    expect(onChange).toHaveBeenLastCalledWith([
      { type: 'paragraph', data: { body: 'B' } },
      { type: 'heading', data: { text: 'A' } },
    ]);
  });

  it('moves a block down when the move-down button is clicked', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <BuilderInput
        field={buildField()}
        value={[
          { type: 'heading', data: { text: 'A' } },
          { type: 'paragraph', data: { body: 'B' } },
        ]}
        onChange={onChange}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Move block 1 down' }));

    expect(onChange).toHaveBeenLastCalledWith([
      { type: 'paragraph', data: { body: 'B' } },
      { type: 'heading', data: { text: 'A' } },
    ]);
  });

  it('disables move-up on the first block and move-down on the last block', () => {
    const onChange = vi.fn();
    render(
      <BuilderInput
        field={buildField()}
        value={[
          { type: 'heading', data: { text: 'A' } },
          { type: 'heading', data: { text: 'B' } },
        ]}
        onChange={onChange}
      />,
    );

    expect(screen.getByRole('button', { name: 'Move block 1 up' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Move block 2 down' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Move block 2 up' })).not.toBeDisabled();
    expect(screen.getByRole('button', { name: 'Move block 1 down' })).not.toBeDisabled();
  });

  it('disables the add button when items.length reaches maxItems', () => {
    const onChange = vi.fn();
    render(
      <BuilderInput
        field={buildField({ maxItems: 2 })}
        value={[
          { type: 'heading', data: { text: 'A' } },
          { type: 'heading', data: { text: 'B' } },
        ]}
        onChange={onChange}
      />,
    );

    expect(screen.getByRole('button', { name: 'Add block' })).toBeDisabled();
  });

  it('removes a block when the remove button is clicked', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <BuilderInput
        field={buildField()}
        value={[
          { type: 'heading', data: { text: 'A' } },
          { type: 'paragraph', data: { body: 'B' } },
        ]}
        onChange={onChange}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Remove block 1' }));

    expect(onChange).toHaveBeenLastCalledWith([{ type: 'paragraph', data: { body: 'B' } }]);
    expect(screen.getAllByRole('article')).toHaveLength(1);
  });

  it('emits [{type, data}] without leaking the internal __id', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<BuilderInput field={buildField()} value={[]} onChange={onChange} />);

    await user.click(screen.getByRole('button', { name: 'Add block' }));
    await user.click(screen.getByRole('menuitem', { name: /Heading/ }));

    const last = onChange.mock.calls.at(-1)?.[0] as Array<Record<string, unknown>>;
    expect(last).toHaveLength(1);
    const first = last[0];
    expect(first).toBeDefined();
    expect(Object.keys(first as object).sort()).toEqual(['data', 'type']);
    expect((first as { __id?: unknown }).__id).toBeUndefined();
  });

  it('closes the picker when ESC is pressed', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<BuilderInput field={buildField()} value={[]} onChange={onChange} />);

    await user.click(screen.getByRole('button', { name: 'Add block' }));
    expect(screen.getByRole('menu', { name: 'Add block' })).toBeInTheDocument();

    await user.keyboard('{Escape}');

    expect(screen.queryByRole('menu', { name: 'Add block' })).toBeNull();
  });

  it('renders a drag handle for each block when reorderable=true', () => {
    const onChange = vi.fn();
    render(
      <BuilderInput
        field={buildField()}
        value={[
          { type: 'heading', data: { text: 'A' } },
          { type: 'heading', data: { text: 'B' } },
        ]}
        onChange={onChange}
      />,
    );

    expect(screen.getByLabelText('Drag to reorder block 1')).toBeInTheDocument();
    expect(screen.getByLabelText('Drag to reorder block 2')).toBeInTheDocument();
  });

  it('hides the drag handle when reorderable=false', () => {
    const onChange = vi.fn();
    render(
      <BuilderInput
        field={buildField({ reorderable: false })}
        value={[
          { type: 'heading', data: { text: 'A' } },
          { type: 'heading', data: { text: 'B' } },
        ]}
        onChange={onChange}
      />,
    );

    expect(screen.queryByLabelText('Drag to reorder block 1')).toBeNull();
    expect(screen.queryByLabelText('Drag to reorder block 2')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Move block 1 up' })).toBeNull();
  });

  it('reorders blocks when dnd-kit fires onDragEnd', () => {
    const onChange = vi.fn();
    render(
      <BuilderInput
        field={buildField()}
        value={[
          { type: 'heading', data: { text: 'A' } },
          { type: 'paragraph', data: { body: 'B' } },
        ]}
        onChange={onChange}
      />,
    );

    const ids = capturedDnd.items;
    const onDragEnd = capturedDnd.onDragEnd;
    if (!ids || !onDragEnd) throw new Error('dnd-kit not captured');
    expect(ids).toHaveLength(2);

    onDragEnd({
      active: { id: ids[0] as string },
      over: { id: ids[1] as string },
    });

    expect(onChange).toHaveBeenLastCalledWith([
      { type: 'paragraph', data: { body: 'B' } },
      { type: 'heading', data: { text: 'A' } },
    ]);
  });

  it('closes the picker when the backdrop is clicked', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<BuilderInput field={buildField()} value={[]} onChange={onChange} />);

    await user.click(screen.getByRole('button', { name: 'Add block' }));
    await user.click(screen.getByTestId('builder-picker-backdrop'));

    expect(screen.queryByRole('menu', { name: 'Add block' })).toBeNull();
  });
});
