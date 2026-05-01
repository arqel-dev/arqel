import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, ...rest }: { children: React.ReactNode } & Record<string, unknown>) => (
    <a {...rest}>{children}</a>
  ),
}));

import { PluginCard } from '../Components/Marketplace/PluginCard';
import type { Plugin } from '../types';

const basePlugin: Plugin = {
  id: 1,
  slug: 'awesome',
  name: 'Awesome Plugin',
  description: 'Does a thing.',
  type: 'field',
  github_url: 'https://github.com/x/awesome',
  license: 'MIT',
  install_count: 12345,
};

describe('<PluginCard />', () => {
  it('renders name, description and slug link', () => {
    render(<PluginCard plugin={basePlugin} />);
    expect(screen.getByText('Awesome Plugin')).toBeInTheDocument();
    expect(screen.getByText('Does a thing.')).toBeInTheDocument();
    expect(screen.getByRole('link')).toHaveAttribute('href', '/plugins/awesome');
  });

  it('shows the type badge', () => {
    render(<PluginCard plugin={basePlugin} />);
    expect(screen.getByTestId('type-badge')).toHaveTextContent('field');
  });

  it('formats large install counts as Xk / XM', () => {
    render(<PluginCard plugin={{ ...basePlugin, install_count: 12345 }} />);
    expect(screen.getByTestId('install-count')).toHaveTextContent('12.3k installs');
  });
});
