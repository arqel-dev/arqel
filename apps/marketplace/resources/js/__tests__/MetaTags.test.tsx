import { render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

type HeadProps = { title?: string; children?: React.ReactNode };

vi.mock('@inertiajs/react', () => ({
  Head: ({ title, children }: HeadProps) => (
    <div data-testid="inertia-head" data-title={title}>
      {children}
    </div>
  ),
}));

import { MetaTags } from '../Components/Marketplace/MetaTags';

describe('<MetaTags />', () => {
  it('renders title and falls back to default og:image when none provided', () => {
    const { getByTestId, container } = render(
      <MetaTags title="Test Title" description="Test description" />,
    );
    const head = getByTestId('inertia-head');
    expect(head.dataset.title).toBe('Test Title');

    const ogImage = container.querySelector('meta[property="og:image"]');
    expect(ogImage?.getAttribute('content')).toBe('/images/og/marketplace-default.png');
  });

  it('renders custom og:image when provided', () => {
    const { container } = render(
      <MetaTags
        title="X"
        description="Y"
        ogImage="https://cdn.example.com/screenshot.png"
      />,
    );
    const ogImage = container.querySelector('meta[property="og:image"]');
    expect(ogImage?.getAttribute('content')).toBe('https://cdn.example.com/screenshot.png');
  });

  it('emits JSON-LD script tag when jsonLd prop is passed', () => {
    const jsonLd = { '@context': 'https://schema.org', '@type': 'Product', name: 'X' };
    const { container } = render(
      <MetaTags title="X" description="Y" jsonLd={jsonLd} />,
    );
    const ldScript = container.querySelector('script[type="application/ld+json"]');
    expect(ldScript).not.toBeNull();
    expect(ldScript?.innerHTML).toContain('"@type":"Product"');
  });

  it('escapes < in JSON-LD payload to prevent script injection', () => {
    const malicious = { name: '</script><script>alert(1)</script>' };
    const { container } = render(
      <MetaTags title="X" description="Y" jsonLd={malicious} />,
    );
    const ldScript = container.querySelector('script[type="application/ld+json"]');
    const html = ldScript?.innerHTML ?? '';
    // Literal `</script>` must NOT appear — should be unicode-escaped.
    expect(html).not.toContain('</script>');
    expect(html).toContain('\\u003c');
  });

  it('uses customizable twitter card type', () => {
    const { container } = render(
      <MetaTags title="X" description="Y" twitterCard="summary" />,
    );
    const tw = container.querySelector('meta[name="twitter:card"]');
    expect(tw?.getAttribute('content')).toBe('summary');
  });

  it('defaults og:type to website when not specified', () => {
    const { container } = render(<MetaTags title="X" description="Y" />);
    const ogType = container.querySelector('meta[property="og:type"]');
    expect(ogType?.getAttribute('content')).toBe('website');
  });
});
