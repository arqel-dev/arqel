import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { StatCard, type StatCardWidget } from '../../src/widgets/StatCard.js';

function makeWidget(overrides: Partial<StatCardWidget> = {}): StatCardWidget {
  return {
    name: 'total_users',
    type: 'stat',
    heading: 'Total Users',
    value: 1234,
    color: 'primary',
    ...overrides,
  };
}

describe('StatCard', () => {
  it('renders heading and big-number value', () => {
    render(<StatCard widget={makeWidget()} />);
    expect(screen.getByRole('heading', { level: 2 })).toHaveTextContent('Total Users');
    expect(screen.getByText('1234')).toBeInTheDocument();
  });

  it('renders description and statDescription with icon glyph', () => {
    render(
      <StatCard
        widget={makeWidget({
          description: 'Active accounts',
          statDescription: '+12% vs last week',
          descriptionIcon: 'trending-up',
        })}
      />,
    );
    expect(screen.getByText('Active accounts')).toBeInTheDocument();
    expect(screen.getByText('+12% vs last week')).toBeInTheDocument();
    expect(screen.getByTestId('stat-card-icon')).toHaveTextContent('trending-up');
  });

  it('renders sparkline polyline when chart array present', () => {
    const { container } = render(<StatCard widget={makeWidget({ chart: [1, 5, 2, 8, 3, 6] })} />);
    const svg = screen.getByTestId('stat-card-sparkline');
    expect(svg).toBeInTheDocument();
    const polyline = container.querySelector('polyline');
    expect(polyline).not.toBeNull();
    expect(polyline?.getAttribute('points')).toMatch(/^[0-9.,\s]+$/);
  });

  it('does not render sparkline when chart is missing or too short', () => {
    render(<StatCard widget={makeWidget({ chart: null })} />);
    expect(screen.queryByTestId('stat-card-sparkline')).toBeNull();
  });

  it('does not render sparkline for single-point arrays', () => {
    render(<StatCard widget={makeWidget({ chart: [42] })} />);
    expect(screen.queryByTestId('stat-card-sparkline')).toBeNull();
  });

  it('wraps body in <a href> when url is set', () => {
    render(<StatCard widget={makeWidget({ url: '/admin/users' })} />);
    const link = screen.getByTestId('stat-card-link');
    expect(link.tagName).toBe('A');
    expect(link).toHaveAttribute('href', '/admin/users');
  });

  it('does not wrap in <a> when url is absent', () => {
    render(<StatCard widget={makeWidget()} />);
    expect(screen.queryByTestId('stat-card-link')).toBeNull();
  });

  it('renders accessible heading via <h2>', () => {
    render(<StatCard widget={makeWidget({ heading: 'Revenue' })} />);
    const h2 = screen.getByRole('heading', { level: 2 });
    expect(h2).toHaveTextContent('Revenue');
  });

  it('falls back to em-dash placeholder when value is null', () => {
    render(<StatCard widget={makeWidget({ value: null })} />);
    expect(screen.getByText('—')).toBeInTheDocument();
  });
});
