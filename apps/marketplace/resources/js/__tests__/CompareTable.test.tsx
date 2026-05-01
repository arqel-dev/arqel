import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { CompareTable } from '../Components/Marketplace/CompareTable';
import type { Plugin } from '../types';

function makePlugin(overrides: Partial<Plugin>): Plugin {
  return {
    id: 1,
    slug: 'p',
    name: 'P',
    description: '',
    type: 'field',
    github_url: '',
    license: 'MIT',
    ...overrides,
  };
}

describe('<CompareTable />', () => {
  it('renders headers and rows for two plugins', () => {
    const a = makePlugin({ id: 1, slug: 'a', name: 'Alpha' });
    const b = makePlugin({ id: 2, slug: 'b', name: 'Beta' });
    render(<CompareTable plugins={[a, b]} />);
    expect(screen.getByTestId('compare-header-a')).toHaveTextContent('Alpha');
    expect(screen.getByTestId('compare-header-b')).toHaveTextContent('Beta');
    expect(screen.getByTestId('compare-row-price')).toBeInTheDocument();
    expect(screen.getByTestId('compare-row-license')).toBeInTheDocument();
  });

  it('flags differing values with data-differs=true', () => {
    const a = makePlugin({ id: 1, slug: 'a', name: 'A', install_count: 100, license: 'MIT' });
    const b = makePlugin({ id: 2, slug: 'b', name: 'B', install_count: 200, license: 'MIT' });
    render(<CompareTable plugins={[a, b]} />);
    expect(screen.getByTestId('compare-cell-installs-a')).toHaveAttribute('data-differs', 'true');
    expect(screen.getByTestId('compare-cell-license-a')).toHaveAttribute('data-differs', 'false');
  });

  it('renders three plugin columns', () => {
    const plugins = [
      makePlugin({ id: 1, slug: 'a', name: 'A' }),
      makePlugin({ id: 2, slug: 'b', name: 'B' }),
      makePlugin({ id: 3, slug: 'c', name: 'C' }),
    ];
    render(<CompareTable plugins={plugins} />);
    expect(screen.getByTestId('compare-header-a')).toBeInTheDocument();
    expect(screen.getByTestId('compare-header-b')).toBeInTheDocument();
    expect(screen.getByTestId('compare-header-c')).toBeInTheDocument();
  });
});
