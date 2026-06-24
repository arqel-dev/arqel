import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const localeRef = { current: 'en' };

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, ...rest }: { children: React.ReactNode } & Record<string, unknown>) => (
    <a {...rest}>{children}</a>
  ),
  usePage: () => ({ props: { i18n: { locale: localeRef.current } } }),
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

  it('formats large install counts with locale-aware compact notation (en)', () => {
    localeRef.current = 'en';
    render(<PluginCard plugin={{ ...basePlugin, install_count: 12345 }} />);
    expect(screen.getByTestId('install-count')).toHaveTextContent('12K installs');
  });

  it('uses pt-BR compact notation when the active locale is pt_BR', () => {
    localeRef.current = 'pt_BR';
    render(<PluginCard plugin={{ ...basePlugin, install_count: 12345 }} />);
    expect(screen.getByTestId('install-count')).toHaveTextContent('12 mil installs');
    localeRef.current = 'en';
  });

  it('formats the star count with locale-aware grouping (pt_BR)', () => {
    localeRef.current = 'pt_BR';
    render(<PluginCard plugin={{ ...basePlugin, stars: 12345 }} />);
    expect(screen.getByTestId('stars')).toHaveTextContent('12.345');
    localeRef.current = 'en';
  });
});
