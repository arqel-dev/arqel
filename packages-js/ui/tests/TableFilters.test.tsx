import type { FilterSchema } from '@arqel-dev/types/tables';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { TableFilters } from '../src/table/TableFilters.js';

const filters: FilterSchema[] = [
  {
    type: 'select',
    name: 'role',
    label: 'Role',
    persist: false,
    default: null,
    props: {
      options: [
        { value: 'admin', label: 'Admin' },
        { value: 'user', label: 'User' },
      ],
    },
  },
  {
    type: 'text',
    name: 'q',
    label: 'Query',
    persist: false,
    default: null,
    props: {},
  },
];

describe('TableFilters', () => {
  it('emits change for select filter', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<TableFilters filters={filters} values={{}} onChange={onChange} />);
    await user.selectOptions(screen.getByLabelText('Role'), 'admin');
    expect(onChange).toHaveBeenCalledWith('role', 'admin');
  });

  it('emits change for text filter and null on empty', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<TableFilters filters={filters} values={{}} onChange={onChange} />);
    await user.type(screen.getByLabelText('Query'), 'a');
    expect(onChange).toHaveBeenCalledWith('q', 'a');
  });

  it('shows clear button only when there are active filters', () => {
    const onChange = vi.fn();
    const onClearAll = vi.fn();
    const { rerender } = render(
      <TableFilters filters={filters} values={{}} onChange={onChange} onClearAll={onClearAll} />,
    );
    expect(screen.queryByRole('button', { name: /clear filters/i })).toBeNull();

    rerender(
      <TableFilters
        filters={filters}
        values={{ role: 'admin' }}
        onChange={onChange}
        onClearAll={onClearAll}
      />,
    );
    expect(screen.getByRole('button', { name: /clear filters \(1\)/i })).toBeInTheDocument();
  });
});
