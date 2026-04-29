import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { TableToolbar } from '../src/table/TableToolbar.js';

describe('TableToolbar', () => {
  it('renders search and filters slots', () => {
    render(
      <TableToolbar
        search={<input data-testid="search" />}
        filters={<div data-testid="filters" />}
      />,
    );
    expect(screen.getByTestId('search')).toBeInTheDocument();
    expect(screen.getByTestId('filters')).toBeInTheDocument();
  });

  it('hides bulk bar when nothing selected', () => {
    render(<TableToolbar selectedCount={0} bulkActions={<button type="button">Delete</button>} />);
    expect(screen.queryByRole('region', { name: 'Bulk actions' })).toBeNull();
  });

  it('shows bulk bar with count and clear button', async () => {
    const user = userEvent.setup();
    const onClearSelection = vi.fn();
    render(
      <TableToolbar
        selectedCount={3}
        bulkActions={<button type="button">Delete</button>}
        onClearSelection={onClearSelection}
      />,
    );
    expect(screen.getByText('3 selected')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Clear' }));
    expect(onClearSelection).toHaveBeenCalled();
  });
});
