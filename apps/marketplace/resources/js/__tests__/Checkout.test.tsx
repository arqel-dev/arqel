import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }: { title: string }) => <title>{title}</title>,
}));

import Checkout from '../Pages/Marketplace/Checkout';
import type { Plugin } from '../types';

const plugin: Plugin = {
  id: 1,
  slug: 'paid-plugin',
  name: 'Paid Plugin',
  description: 'Premium thing',
  type: 'field',
  github_url: 'https://github.com/x/paid',
  license: 'MIT',
  price_cents: 2500,
  currency: 'USD',
};

const summary = {
  price_cents: 2500,
  currency: 'USD',
  fee_estimate_cents: 500,
  total_cents: 2500,
};

describe('<Checkout />', () => {
  it('renders summary card with plugin name and total', () => {
    render(<Checkout plugin={plugin} summary={summary} />);
    expect(screen.getByTestId('summary-card')).toHaveTextContent('Paid Plugin');
    expect(screen.getByTestId('summary-total')).toHaveTextContent('USD 25.00');
  });

  it('submits a POST form to the initiate endpoint when proceed clicked', () => {
    render(<Checkout plugin={plugin} summary={summary} />);
    const button = screen.getByTestId('proceed-payment') as HTMLButtonElement;
    const form = button.closest('form');
    expect(form).not.toBeNull();
    expect(form?.method.toLowerCase()).toBe('post');
    expect(form?.getAttribute('action')).toBe('/checkout/paid-plugin/initiate');

    const submit = vi.fn((e: SubmitEvent) => e.preventDefault());
    form?.addEventListener('submit', submit as unknown as EventListener);
    fireEvent.click(button);
    expect(submit).toHaveBeenCalled();
  });

  it('renders fee estimate from summary', () => {
    render(<Checkout plugin={plugin} summary={summary} />);
    expect(screen.getByTestId('summary-fee')).toHaveTextContent('USD 5.00');
    expect(screen.getByTestId('summary-price')).toHaveTextContent('USD 25.00');
  });
});
