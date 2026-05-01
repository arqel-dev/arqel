import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, ...rest }: { children: React.ReactNode } & Record<string, unknown>) => (
    <a {...rest}>{children}</a>
  ),
}));

import { PublisherBadge } from '../Components/Marketplace/PublisherBadge';
import type { PublisherSnapshot } from '../types';

const base: PublisherSnapshot = {
  id: 1,
  slug: 'acme',
  name: 'Acme Corp',
};

describe('<PublisherBadge />', () => {
  it('renders the publisher name', () => {
    render(<PublisherBadge publisher={base} />);
    expect(screen.getByTestId('publisher-name')).toHaveTextContent('Acme Corp');
  });

  it('shows verified indicator when publisher.verified is true', () => {
    render(<PublisherBadge publisher={{ ...base, verified: true }} />);
    expect(screen.getByTestId('publisher-verified')).toBeInTheDocument();
  });

  it('renders avatar fallback initials when avatar_url is missing', () => {
    render(<PublisherBadge publisher={base} />);
    expect(screen.getByTestId('publisher-avatar-fallback')).toHaveTextContent('AC');
    expect(screen.queryByTestId('publisher-avatar')).not.toBeInTheDocument();
  });
});
