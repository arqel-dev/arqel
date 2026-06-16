import type { ColumnSchema } from '@arqel-dev/types/tables';
import { fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { DataTable } from '../src/table/DataTable.js';

const columns: ColumnSchema[] = [
  {
    type: 'text',
    name: 'name',
    label: 'Name',
    sortable: true,
    searchable: true,
    copyable: false,
    hidden: false,
    hiddenOnMobile: false,
    align: 'start',
    width: null,
    tooltip: null,
    props: {},
  },
  {
    type: 'badge',
    name: 'status',
    label: 'Status',
    sortable: false,
    searchable: false,
    copyable: false,
    hidden: false,
    hiddenOnMobile: false,
    align: 'start',
    width: null,
    tooltip: null,
    props: { options: [{ value: 'active', label: 'Active' }] },
  },
];

const records = [
  { id: 1, name: 'Alice', status: 'active' },
  { id: 2, name: 'Bob', status: 'active' },
  { id: 3, name: 'Carol', status: 'active' },
];

describe('DataTable', () => {
  it('renders columns and rows', () => {
    render(<DataTable columns={columns} records={records} />);
    const table = within(screen.getByRole('table'));
    expect(table.getByText('Name')).toBeInTheDocument();
    expect(table.getByText('Alice')).toBeInTheDocument();
    expect(table.getAllByText('Active')).toHaveLength(3);
  });

  it('shows empty state when no records', () => {
    render(<DataTable columns={columns} records={[]} emptyState="Nada por aqui" />);
    const table = within(screen.getByRole('table'));
    expect(table.getByText('Nada por aqui')).toBeInTheDocument();
  });

  it('shows loading row', () => {
    render(<DataTable columns={columns} records={[]} loading />);
    const table = within(screen.getByRole('table'));
    expect(table.getByText('Loading…')).toBeInTheDocument();
  });

  it('emits sort change on header click for sortable columns', async () => {
    const user = userEvent.setup();
    const onSortChange = vi.fn();
    render(<DataTable columns={columns} records={records} onSortChange={onSortChange} />);
    await user.click(screen.getByRole('button', { name: /name/i }));
    expect(onSortChange).toHaveBeenCalledWith('name', 'asc');
  });

  it('toggles single row selection', async () => {
    const user = userEvent.setup();
    const onSelectionChange = vi.fn();
    render(
      <DataTable
        columns={columns}
        records={records}
        enableSelection
        selectedIds={[]}
        onSelectionChange={onSelectionChange}
      />,
    );
    const table = within(screen.getByRole('table'));
    await user.click(table.getByLabelText('Select row 1'));
    expect(onSelectionChange).toHaveBeenCalledWith([1]);
  });

  it('selects a range with Shift+click', () => {
    const onSelectionChange = vi.fn();
    const { rerender } = render(
      <DataTable
        columns={columns}
        records={records}
        enableSelection
        selectedIds={[]}
        onSelectionChange={onSelectionChange}
      />,
    );
    // First click: select row 1 (index 0)
    fireEvent.click(within(screen.getByRole('table')).getByLabelText('Select row 1'));
    expect(onSelectionChange).toHaveBeenLastCalledWith([1]);

    rerender(
      <DataTable
        columns={columns}
        records={records}
        enableSelection
        selectedIds={[1]}
        onSelectionChange={onSelectionChange}
      />,
    );

    // Shift+click on row 3 (index 2) should select 1..3
    fireEvent.click(within(screen.getByRole('table')).getByLabelText('Select row 3'), {
      shiftKey: true,
    });
    expect(onSelectionChange).toHaveBeenLastCalledWith(expect.arrayContaining([1, 2, 3]));
  });

  it('renders rowActions cell', () => {
    render(
      <DataTable
        columns={columns}
        records={records}
        rowActions={(record) => <button type="button">edit-{record.id}</button>}
      />,
    );
    const table = within(screen.getByRole('table'));
    expect(table.getByRole('button', { name: 'edit-1' })).toBeInTheDocument();
  });
});
