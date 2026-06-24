import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const localeRef = { current: 'en' };

vi.mock('@inertiajs/react', () => ({
  Head: ({ children }: { children?: React.ReactNode }) => <>{children}</>,
  Link: ({ children, ...rest }: { children: React.ReactNode } & Record<string, unknown>) => (
    <a {...rest}>{children}</a>
  ),
  usePage: () => ({ props: { i18n: { locale: localeRef.current } } }),
}));

import PluginDetail from '../Pages/Marketplace/PluginDetail';
import type { Paginator, Plugin, PluginReview, PluginVersion } from '../types';

const plugin: Plugin = {
  id: 1,
  slug: 'paid',
  name: 'Paid Plugin',
  description: 'A premium plugin.',
  type: 'field',
  github_url: 'https://github.com/x/paid',
  license: 'MIT',
};

const versions: PluginVersion[] = [
  { id: 1, version: '1.2.0', released_at: '2026-06-23T00:00:00Z', changelog: 'Stuff' },
];

const reviews: Paginator<PluginReview> = {
  data: [],
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
};

describe('<PluginDetail /> version dates', () => {
  it('formats the version release date for the active locale (pt_BR)', () => {
    localeRef.current = 'pt_BR';
    render(<PluginDetail plugin={plugin} versions={versions} reviews={reviews} related={[]} />);
    fireEvent.click(screen.getByText('Versões'));
    // pt-BR medium date renders as "<day> de jun. de 2026" — never the raw ISO string.
    expect(screen.getByText(/de jun\. de 2026/)).toBeInTheDocument();
    expect(screen.queryByText('2026-06-23T00:00:00Z')).toBeNull();
    localeRef.current = 'en';
  });
});
