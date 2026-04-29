import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { TablePagination } from '../src/table/TablePagination.js';

describe('TablePagination', () => {
  it('renders range and total', () => {
    render(
      <TablePagination
        meta={{ currentPage: 2, lastPage: 5, perPage: 10, total: 47 }}
        onPageChange={() => {}}
      />,
    );
    expect(screen.getByText(/11–20 of 47/)).toBeInTheDocument();
    expect(screen.getByText('2 / 5')).toBeInTheDocument();
  });

  it('disables prev on first page and next on last', () => {
    const { rerender } = render(
      <TablePagination
        meta={{ currentPage: 1, lastPage: 5, perPage: 10, total: 47 }}
        onPageChange={() => {}}
      />,
    );
    expect(screen.getByRole('button', { name: /previous/i })).toBeDisabled();
    expect(screen.getByRole('button', { name: /next/i })).not.toBeDisabled();

    rerender(
      <TablePagination
        meta={{ currentPage: 5, lastPage: 5, perPage: 10, total: 47 }}
        onPageChange={() => {}}
      />,
    );
    expect(screen.getByRole('button', { name: /next/i })).toBeDisabled();
  });

  it('emits onPageChange', async () => {
    const user = userEvent.setup();
    const onPageChange = vi.fn();
    render(
      <TablePagination
        meta={{ currentPage: 2, lastPage: 5, perPage: 10, total: 47 }}
        onPageChange={onPageChange}
      />,
    );
    await user.click(screen.getByRole('button', { name: /next/i }));
    expect(onPageChange).toHaveBeenCalledWith(3);
  });

  it('shows "No results" when total=0', () => {
    render(
      <TablePagination
        meta={{ currentPage: 1, lastPage: 1, perPage: 10, total: 0 }}
        onPageChange={() => {}}
      />,
    );
    expect(screen.getByText('No results')).toBeInTheDocument();
  });
});
