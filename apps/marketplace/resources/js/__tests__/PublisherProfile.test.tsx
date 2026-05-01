import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, ...rest }: { children: React.ReactNode } & Record<string, unknown>) => (
    <a {...rest}>{children}</a>
  ),
}));

import PublisherProfile from '../Pages/Marketplace/PublisherProfile';
import type { Plugin, Publisher, PublisherStats } from '../types';

const publisher: Publisher = {
  id: 1,
  slug: 'acme',
  name: 'Acme Corp',
  bio: 'We build great plugins.',
  website_url: 'https://acme.dev',
  github_url: 'https://github.com/acme',
  twitter_handle: 'acmecorp',
  verified: true,
};

const plugin: Plugin = {
  id: 10,
  slug: 'acme-widget',
  name: 'Acme Widget',
  description: 'A widget.',
  type: 'widget',
  github_url: 'https://github.com/acme/widget',
  license: 'MIT',
};

const stats: PublisherStats = {
  plugins_count: 1,
  total_downloads: 1234,
  avg_rating: 4.5,
};

describe('<PublisherProfile />', () => {
  it('renders header, bio, stats and plugin grid', () => {
    render(<PublisherProfile publisher={publisher} plugins={[plugin]} stats={stats} />);
    expect(screen.getByText('Acme Corp')).toBeInTheDocument();
    expect(screen.getByTestId('profile-bio')).toHaveTextContent('We build great plugins.');
    expect(screen.getByTestId('stat-plugins')).toHaveTextContent('1');
    expect(screen.getByTestId('stat-downloads')).toHaveTextContent('1.2k');
    expect(screen.getByTestId('stat-rating')).toHaveTextContent('4.5');
    expect(screen.getByText('Acme Widget')).toBeInTheDocument();
  });

  it('renders all social links when provided', () => {
    render(<PublisherProfile publisher={publisher} plugins={[]} stats={stats} />);
    expect(screen.getByTestId('profile-website')).toHaveAttribute('href', 'https://acme.dev');
    expect(screen.getByTestId('profile-github')).toHaveAttribute('href', 'https://github.com/acme');
    expect(screen.getByTestId('profile-twitter')).toHaveAttribute(
      'href',
      'https://twitter.com/acmecorp',
    );
  });

  it('shows empty state when there are no plugins', () => {
    render(
      <PublisherProfile
        publisher={publisher}
        plugins={[]}
        stats={{ plugins_count: 0, total_downloads: 0, avg_rating: 0 }}
      />,
    );
    expect(screen.getByTestId('profile-empty')).toBeInTheDocument();
  });

  it('renders verified badge when publisher.verified is true', () => {
    render(<PublisherProfile publisher={publisher} plugins={[]} stats={stats} />);
    expect(screen.getByTestId('profile-verified')).toBeInTheDocument();
  });
});
