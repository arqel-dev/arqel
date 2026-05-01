import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }: { title: string }) => <title>{title}</title>,
}));

import CheckoutSuccess from '../Pages/Marketplace/CheckoutSuccess';
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

const license = 'ARQ-aaaa-bbbb-cccc-dddd';

describe('<CheckoutSuccess />', () => {
  it('renders license key prominently', () => {
    render(
      <CheckoutSuccess
        plugin={plugin}
        license_key={license}
        download_url="/plugins/paid-plugin/download"
      />,
    );
    expect(screen.getByTestId('license-key')).toHaveTextContent(license);
  });

  it('copy button copies license to clipboard and shows feedback', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(globalThis.navigator, 'clipboard', {
      value: { writeText },
      configurable: true,
    });

    render(
      <CheckoutSuccess
        plugin={plugin}
        license_key={license}
        download_url="/plugins/paid-plugin/download"
      />,
    );
    fireEvent.click(screen.getByTestId('copy-license'));
    await Promise.resolve();
    expect(writeText).toHaveBeenCalledWith(license);
    expect(await screen.findByText('Copiado!')).toBeInTheDocument();
  });

  it('renders the download link with correct URL', () => {
    render(
      <CheckoutSuccess
        plugin={plugin}
        license_key={license}
        download_url="/plugins/paid-plugin/download"
      />,
    );
    expect(screen.getByTestId('download-link')).toHaveAttribute(
      'href',
      '/plugins/paid-plugin/download',
    );
  });

  it('renders celebration emoji and confirmation heading', () => {
    render(
      <CheckoutSuccess
        plugin={plugin}
        license_key={license}
        download_url="/plugins/paid-plugin/download"
      />,
    );
    expect(screen.getByTestId('celebration')).toBeInTheDocument();
    expect(screen.getByText('Compra confirmada!')).toBeInTheDocument();
  });
});
