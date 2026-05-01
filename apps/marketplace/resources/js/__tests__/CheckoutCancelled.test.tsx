import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }: { title: string }) => <title>{title}</title>,
}));

import CheckoutCancelled from '../Pages/Marketplace/CheckoutCancelled';
import type { Plugin } from '../types';

const plugin: Plugin = {
  id: 1,
  slug: 'paid-plugin',
  name: 'Paid Plugin',
  description: 'Premium thing',
  type: 'field',
  github_url: 'https://github.com/x/paid',
  license: 'MIT',
};

describe('<CheckoutCancelled />', () => {
  it('renders cancellation heading and reassurance copy', () => {
    render(<CheckoutCancelled plugin={plugin} />);
    expect(screen.getByText('Compra cancelada')).toBeInTheDocument();
    expect(screen.getByTestId('back-link')).toHaveAttribute('href', '/plugins/paid-plugin');
  });

  it('renders retry link pointing back to checkout', () => {
    render(<CheckoutCancelled plugin={plugin} />);
    expect(screen.getByTestId('retry-link')).toHaveAttribute('href', '/checkout/paid-plugin');
  });
});
