import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { PerformanceMetrics } from '../PerformanceMetrics';

const EMPTY = {
  lcp: null,
  inp: null,
  fid: null,
  cls: null,
  navigationTime: null,
};

describe('<PerformanceMetrics />', () => {
  it('renders the four Web Vitals tiles when metrics are populated', () => {
    render(
      <PerformanceMetrics
        metrics={{ lcp: 1800, inp: 120, fid: null, cls: 0.05, navigationTime: 800 }}
      />,
    );
    const tiles = screen.getAllByTestId('performance-tile');
    expect(tiles).toHaveLength(4);
    const metrics = tiles.map((t) => t.getAttribute('data-metric'));
    expect(metrics).toEqual(['lcp', 'responsiveness', 'cls', 'navigationTime']);
  });

  it('applies status colour coding based on Web Vitals thresholds', () => {
    render(
      <PerformanceMetrics
        metrics={{ lcp: 1800, inp: 350, fid: null, cls: 0.4, navigationTime: 5000 }}
      />,
    );
    const lcp = screen
      .getAllByTestId('performance-tile')
      .find((t) => t.getAttribute('data-metric') === 'lcp');
    const responsiveness = screen
      .getAllByTestId('performance-tile')
      .find((t) => t.getAttribute('data-metric') === 'responsiveness');
    const cls = screen
      .getAllByTestId('performance-tile')
      .find((t) => t.getAttribute('data-metric') === 'cls');
    const nav = screen
      .getAllByTestId('performance-tile')
      .find((t) => t.getAttribute('data-metric') === 'navigationTime');

    expect(lcp).toHaveAttribute('data-status', 'good');
    expect(responsiveness).toHaveAttribute('data-status', 'needs-improvement');
    expect(cls).toHaveAttribute('data-status', 'poor');
    expect(nav).toHaveAttribute('data-status', 'poor');
  });

  it('shows server-side query count + memory when supplied', () => {
    render(
      <PerformanceMetrics
        metrics={EMPTY}
        server={{ queryCount: 42, memoryUsage: 5 * 1024 * 1024 }}
      />,
    );
    expect(screen.getByTestId('performance-server')).toBeInTheDocument();
    expect(screen.getByTestId('performance-query-count')).toHaveTextContent('42');
    expect(screen.getByTestId('performance-memory')).toHaveTextContent('5.0 MB');
  });

  it('renders an empty state when nothing has been captured', () => {
    render(<PerformanceMetrics metrics={EMPTY} />);
    expect(screen.getByTestId('performance-empty')).toHaveTextContent('No performance metrics');
    expect(screen.queryByTestId('performance-grid')).not.toBeInTheDocument();
  });
});
