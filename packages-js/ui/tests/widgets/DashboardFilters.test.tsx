import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import {
  type DashboardFilterPayload,
  DashboardFilters,
} from '../../src/widgets/DashboardFilters.js';

describe('DashboardFilters', () => {
  it('returns null when no filters supplied', () => {
    const { container } = render(<DashboardFilters filters={[]} values={{}} onChange={() => {}} />);
    expect(container.firstChild).toBeNull();
  });

  it('renders a select control with options', () => {
    const filters: DashboardFilterPayload[] = [
      {
        name: 'status',
        type: 'select',
        label: 'Status',
        options: { open: 'Open', closed: 'Closed' },
      },
    ];
    render(<DashboardFilters filters={filters} values={{}} onChange={() => {}} />);
    const select = screen.getByLabelText('Status') as HTMLSelectElement;
    expect(select.tagName).toBe('SELECT');
    expect(screen.getByRole('option', { name: 'Open' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Closed' })).toBeInTheDocument();
  });

  it('propagates select onChange', () => {
    const onChange = vi.fn();
    const filters: DashboardFilterPayload[] = [
      {
        name: 'status',
        type: 'select',
        label: 'Status',
        options: [
          { value: 'open', label: 'Open' },
          { value: 'closed', label: 'Closed' },
        ],
      },
    ];
    render(<DashboardFilters filters={filters} values={{}} onChange={onChange} />);
    fireEvent.change(screen.getByLabelText('Status'), {
      target: { value: 'open' },
    });
    expect(onChange).toHaveBeenCalledWith('status', 'open');
  });

  it('renders date_range with two date inputs and propagates from change', () => {
    const onChange = vi.fn();
    const filters: DashboardFilterPayload[] = [
      { name: 'period', type: 'date_range', label: 'Period' },
    ];
    render(<DashboardFilters filters={filters} values={{}} onChange={onChange} />);
    const fromInput = screen.getByLabelText('Period from') as HTMLInputElement;
    const toInput = screen.getByLabelText('Period to') as HTMLInputElement;
    expect(fromInput.type).toBe('date');
    expect(toInput.type).toBe('date');

    fireEvent.change(fromInput, { target: { value: '2026-01-01' } });
    expect(onChange).toHaveBeenCalledWith('period', {
      from: '2026-01-01',
      to: null,
    });
  });

  it('drops unknown filter types silently', () => {
    const filters: DashboardFilterPayload[] = [{ name: 'foo', type: 'unsupported', label: 'Foo' }];
    const { container } = render(
      <DashboardFilters filters={filters} values={{}} onChange={() => {}} />,
    );
    // wrapper still renders, but no controls inside.
    const wrapper = container.querySelector('[data-testid="dashboard-filters"]');
    expect(wrapper).not.toBeNull();
    expect(wrapper?.querySelector('select, input')).toBeNull();
  });
});
