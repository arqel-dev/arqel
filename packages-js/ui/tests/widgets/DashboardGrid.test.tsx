import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { DashboardGrid, type DashboardPayload } from '../../src/widgets/DashboardGrid.js';

const dashboard = (overrides: Partial<DashboardPayload> = {}): DashboardPayload => ({
  id: 'overview',
  label: 'Overview',
  description: 'KPIs and recent activity',
  columns: 3,
  widgets: [
    {
      name: 'orders-count',
      type: 'stat',
      heading: 'Orders',
      data: { value: 42 },
    },
    {
      name: 'recent-orders',
      type: 'table',
      heading: 'Recent orders',
      columns: [{ name: 'id', label: 'ID' }],
      records: [{ id: 1 }],
      limit: 10,
    },
  ],
  ...overrides,
});

describe('DashboardGrid', () => {
  it('renders heading + description + each widget slot', () => {
    render(<DashboardGrid dashboard={dashboard()} />);
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Overview');
    expect(screen.getByText('KPIs and recent activity')).toBeInTheDocument();
    expect(document.querySelector('[data-widget-slot="orders-count"]')).not.toBeNull();
    expect(document.querySelector('[data-widget-slot="recent-orders"]')).not.toBeNull();
  });

  it('applies grid-cols-N when columns is an int', () => {
    render(<DashboardGrid dashboard={dashboard({ columns: 4 })} />);
    const grid = screen.getByTestId('dashboard-grid');
    expect(grid.className).toContain('grid-cols-4');
  });

  it('emits responsive grid classes from a breakpoint map', () => {
    render(<DashboardGrid dashboard={dashboard({ columns: { sm: 1, md: 2, lg: 3 } })} />);
    const grid = screen.getByTestId('dashboard-grid');
    expect(grid.className).toContain('sm:grid-cols-1');
    expect(grid.className).toContain('md:grid-cols-2');
    expect(grid.className).toContain('lg:grid-cols-3');
  });

  it('renders without filter bar when filters are absent', () => {
    render(<DashboardGrid dashboard={dashboard()} />);
    expect(screen.queryByTestId('dashboard-filters')).toBeNull();
  });

  it('renders filter bar when filters are declared', () => {
    render(
      <DashboardGrid
        dashboard={dashboard({
          filters: [
            {
              name: 'status',
              type: 'select',
              label: 'Status',
              options: { open: 'Open' },
            },
          ],
        })}
      />,
    );
    expect(screen.getByTestId('dashboard-filters')).toBeInTheDocument();
    expect(screen.getByLabelText('Status')).toBeInTheDocument();
  });
});
