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
    // Range summary now flows through the `table.pagination.showing` key
    // (English fallback) instead of a hand-built ' of ' literal.
    expect(screen.getByText('Showing 11 to 20 of 47 results')).toBeInTheDocument();
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

  it('shows the empty fallback when total=0', () => {
    render(
      <TablePagination
        meta={{ currentPage: 1, lastPage: 1, perPage: 10, total: 0 }}
        onPageChange={() => {}}
      />,
    );
    expect(screen.getByText('No records found.')).toBeInTheDocument();
  });

  it('translates the range summary via table.pagination.showing', () => {
    pageMock.mockReturnValue({
      props: {
        i18n: {
          locale: 'pt_BR',
          available: ['pt_BR'],
          translations: {
            table: {
              pagination: { showing: 'Exibindo :from a :to de :total resultados' },
            },
          },
        },
      },
    });
    render(
      <TablePagination
        meta={{ currentPage: 2, lastPage: 5, perPage: 10, total: 47 }}
        onPageChange={() => {}}
      />,
    );
    expect(screen.getByText('Exibindo 11 a 20 de 47 resultados')).toBeInTheDocument();
  });

  it('groups large counts using the active locale (pt_BR)', () => {
    pageMock.mockReturnValue({
      props: { i18n: { locale: 'pt_BR', available: ['pt_BR'], translations: {} } },
    });
    render(
      <TablePagination
        meta={{ currentPage: 1, lastPage: 1235, perPage: 10, total: 12345 }}
        onPageChange={() => {}}
      />,
    );
    // pt-BR groups thousands with a dot (12.345), not the raw '12345'.
    expect(screen.getByText('Showing 1 to 10 of 12.345 results')).toBeInTheDocument();
  });

  it('groups large counts using the active locale (en)', () => {
    pageMock.mockReturnValue({
      props: { i18n: { locale: 'en', available: ['en'], translations: {} } },
    });
    render(
      <TablePagination
        meta={{ currentPage: 1, lastPage: 1235, perPage: 10, total: 12345 }}
        onPageChange={() => {}}
      />,
    );
    // en groups thousands with a comma (12,345).
    expect(screen.getByText('Showing 1 to 10 of 12,345 results')).toBeInTheDocument();
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
              // Short visible labels and descriptive aria-labels are separate
              // keys: the button text reads "Anterior" while its accessible
              // name reads the fuller "Página anterior".
              pagination: {
                previous: 'Anterior',
                next: 'Próximo',
                previous_page: 'Página anterior',
                next_page: 'Próxima página',
              },
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
    // The accessible name comes from aria-label (the descriptive key); the
    // visible short label is asserted separately via text content.
    expect(screen.getByRole('button', { name: 'Página anterior' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Próxima página' })).toBeInTheDocument();
    expect(screen.getByText('Anterior')).toBeInTheDocument();
    expect(screen.getByText('Próximo')).toBeInTheDocument();

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
