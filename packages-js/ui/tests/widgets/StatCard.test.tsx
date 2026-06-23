import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { StatCard, type StatCardWidget } from '../../src/widgets/StatCard.js';

const { pageMock } = vi.hoisted(() => ({
  pageMock: vi.fn(() => ({ props: {} as Record<string, unknown> })),
}));
vi.mock('@inertiajs/react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/react')>();
  return { ...actual, usePage: pageMock };
});

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
  afterEach(() => {
    pageMock.mockReturnValue({ props: {} });
  });

  it('renders heading and big-number value', () => {
    render(<StatCard widget={makeWidget()} />);
    expect(screen.getByRole('heading', { level: 2 })).toHaveTextContent('Total Users');
    // Numeric values are grouped for the active (en fallback) locale.
    expect(screen.getByText('1,234')).toBeInTheDocument();
  });

  it('formats large numeric values with the active locale grouping (pt_BR ≠ en)', () => {
    pageMock.mockReturnValue({ props: { i18n: { locale: 'en' } } });
    const { unmount } = render(<StatCard widget={makeWidget({ value: 1234567 })} />);
    expect(screen.getByText('1,234,567')).toBeInTheDocument();
    unmount();

    pageMock.mockReturnValue({ props: { i18n: { locale: 'pt_BR' } } });
    render(<StatCard widget={makeWidget({ value: 1234567 })} />);
    // pt-BR uses '.' as the thousands separator.
    expect(screen.getByText('1.234.567')).toBeInTheDocument();
    expect(screen.queryByText('1234567')).toBeNull();
  });

  it('passes pre-formatted string values through verbatim', () => {
    render(<StatCard widget={makeWidget({ value: 'R$ 1.234,00' })} />);
    expect(screen.getByText('R$ 1.234,00')).toBeInTheDocument();
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

  it('renders the secondary line from `statDescription` alongside the chrome `description` (issue #83 B)', () => {
    // `StatWidget::data()` now emits `statDescription` (not `description`), so
    // the chrome subtitle and the comparison line are distinct keys and both
    // render — the data key no longer clobbers the chrome subtitle.
    render(
      <StatCard
        widget={makeWidget({
          description: 'Monthly recurring',
          statDescription: '+12% vs last week',
        })}
      />,
    );
    expect(screen.getByText('Monthly recurring')).toBeInTheDocument();
    expect(screen.getByText('+12% vs last week')).toBeInTheDocument();
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
