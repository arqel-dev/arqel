import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { TablePagination } from '../src/table/TablePagination.js';

const { pageMock } = vi.hoisted(() => ({ pageMock: vi.fn(() => ({ props: {} })) }));
vi.mock('@inertiajs/react', () => ({ usePage: pageMock }));

afterEach(() => {
  pageMock.mockReset();
  pageMock.mockReturnValue({ props: {} });
});

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

  // ── Responsive touch targets (Phase 4): 44px on mobile, dense on >=md ──

  it('Prev/Next/per-page carry the h-11 md:h-8 responsive touch sizing', () => {
    render(
      <TablePagination
        meta={{ currentPage: 2, lastPage: 5, perPage: 10, total: 47 }}
        onPageChange={() => {}}
        onPerPageChange={() => {}}
      />,
    );
    for (const name of [/previous/i, /next/i] as const) {
      const btn = screen.getByRole('button', { name });
      expect(btn.className).toContain('h-11');
      expect(btn.className).toContain('md:h-8');
    }
    const select = screen.getByRole('combobox');
    expect(select.className).toContain('h-11');
    expect(select.className).toContain('md:h-8');
  });

  // ── i18n: chrome translates from the shared dictionary ──

  it('translates Prev/Next/No-results from props.i18n when present', () => {
    pageMock.mockReturnValue({
      props: {
        i18n: {
          locale: 'pt_BR',
          available: ['pt_BR'],
          translations: {
            table: {
              empty: 'Nenhum registro encontrado.',
              pagination: { previous: 'Anterior', next: 'Próximo' },
            },
          },
        },
      },
    });
    const { rerender } = render(
      <TablePagination
        meta={{ currentPage: 2, lastPage: 5, perPage: 10, total: 47 }}
        onPageChange={() => {}}
      />,
    );
    expect(screen.getByRole('button', { name: 'Anterior' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Próximo' })).toBeInTheDocument();

    rerender(
      <TablePagination
        meta={{ currentPage: 1, lastPage: 1, perPage: 10, total: 0 }}
        onPageChange={() => {}}
      />,
    );
    expect(screen.getByText('Nenhum registro encontrado.')).toBeInTheDocument();
  });

  it('falls back to the English literals when no i18n prop is present', () => {
    render(
      <TablePagination
        meta={{ currentPage: 2, lastPage: 5, perPage: 10, total: 47 }}
        onPageChange={() => {}}
      />,
    );
    expect(screen.getByRole('button', { name: /prev/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /next/i })).toBeInTheDocument();
  });
});
