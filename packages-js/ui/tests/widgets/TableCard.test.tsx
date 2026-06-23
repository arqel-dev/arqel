import { render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { TableCardWidget } from '../../src/widgets/TableCard.js';
import { TableCard } from '../../src/widgets/TableCard.js';

const { pageMock } = vi.hoisted(() => ({
  pageMock: vi.fn(() => ({ props: {} as Record<string, unknown> })),
}));
vi.mock('@inertiajs/react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/react')>();
  return { ...actual, usePage: pageMock };
});

const baseWidget = (overrides: Partial<TableCardWidget> = {}): TableCardWidget => ({
  name: 'recent-orders',
  type: 'table',
  heading: 'Recent orders',
  columns: [
    { name: 'id', label: 'ID' },
    { name: 'customer', label: 'Customer' },
  ],
  records: [
    { id: 1, customer: 'Alice' },
    { id: 2, customer: 'Bob' },
  ],
  limit: 10,
  seeAllUrl: null,
  ...overrides,
});

describe('TableCard', () => {
  afterEach(() => {
    pageMock.mockReturnValue({ props: {} });
  });

  it('formats numeric cells with the active locale grouping (pt_BR ≠ en)', () => {
    const widget = baseWidget({
      columns: [{ name: 'revenue', label: 'Revenue' }],
      records: [{ revenue: 1500000 }],
    });

    pageMock.mockReturnValue({ props: { i18n: { locale: 'en' } } });
    const { unmount } = render(<TableCard widget={widget} />);
    expect(screen.getByText('1,500,000')).toBeInTheDocument();
    unmount();

    pageMock.mockReturnValue({ props: { i18n: { locale: 'pt_BR' } } });
    render(<TableCard widget={widget} />);
    expect(screen.getByText('1.500.000')).toBeInTheDocument();
    expect(screen.queryByText('1500000')).toBeNull();
  });

  it('renders column headers + record rows', () => {
    render(<TableCard widget={baseWidget()} />);
    expect(screen.getByText('ID')).toBeInTheDocument();
    expect(screen.getByText('Customer')).toBeInTheDocument();
    expect(screen.getByText('Alice')).toBeInTheDocument();
    expect(screen.getByText('Bob')).toBeInTheDocument();
  });

  it('renders See all link when seeAllUrl is set', () => {
    render(<TableCard widget={baseWidget({ seeAllUrl: '/admin/orders' })} />);
    const link = screen.getByRole('link', { name: /see all/i });
    expect(link).toHaveAttribute('href', '/admin/orders');
  });

  it('omits See all link when seeAllUrl is null', () => {
    render(<TableCard widget={baseWidget()} />);
    expect(screen.queryByRole('link', { name: /see all/i })).toBeNull();
  });

  it('shows alert instead of table when loadError is set', () => {
    render(<TableCard widget={baseWidget({ loadError: 'PDO driver missing' })} />);
    expect(screen.getByRole('alert')).toHaveTextContent('PDO driver missing');
    expect(screen.queryByRole('table')).toBeNull();
  });

  it('renders empty body when records is empty', () => {
    render(<TableCard widget={baseWidget({ records: [] })} />);
    const table = screen.getByRole('table');
    const tbody = table.querySelector('tbody');
    expect(tbody?.children.length).toBe(0);
  });

  it('falls back to column.name when label is missing', () => {
    render(
      <TableCard
        widget={baseWidget({
          columns: [{ name: 'sku' }],
          records: [{ sku: 'A-100' }],
        })}
      />,
    );
    const table = screen.getByRole('table');
    expect(within(table).getByText('sku')).toBeInTheDocument();
    expect(within(table).getByText('A-100')).toBeInTheDocument();
  });
});
