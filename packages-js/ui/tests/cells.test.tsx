import type {
  BadgeColumnSchema,
  BooleanColumnSchema,
  DateColumnSchema,
  IconColumnSchema,
  ImageColumnSchema,
  NumberColumnSchema,
  RelationshipColumnSchema,
  TextColumnSchema,
} from '@arqel-dev/types/tables';
import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { TableCell } from '../src/table/cells.js';

// Controllable `usePage` so locale-aware tests can set `props.i18n.locale`.
// Overrides the empty-bag default from tests/setup.ts.
const { pageMock } = vi.hoisted(() => ({
  pageMock: vi.fn(() => ({ props: {} }) as { props: Record<string, unknown> }),
}));
vi.mock('@inertiajs/react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/react')>();
  return { ...actual, usePage: pageMock };
});

const baseColumn = {
  name: 'value',
  label: 'Value',
  sortable: false,
  searchable: false,
  copyable: false,
  hidden: false,
  hiddenOnMobile: false,
  align: 'start' as const,
  width: null,
  tooltip: null,
};

describe('TableCell', () => {
  it('truncates text with ellipsis', () => {
    const column: TextColumnSchema = {
      ...baseColumn,
      type: 'text',
      props: { truncate: 4, weight: 'bold' },
    };
    render(<TableCell column={column} value="abcdefgh" />);
    expect(screen.getByText('abcd…')).toHaveClass('font-bold');
  });

  it('renders badge with matching option label', () => {
    const column: BadgeColumnSchema = {
      ...baseColumn,
      type: 'badge',
      props: { options: [{ value: 'a', label: 'Active' }], pill: true },
    };
    render(<TableCell column={column} value="a" />);
    expect(screen.getByText('Active')).toBeInTheDocument();
  });

  it('badge applies the colour-token class from props.colors[value]', () => {
    const column: BadgeColumnSchema = {
      ...baseColumn,
      type: 'badge',
      props: { colors: { published: 'green', draft: 'gray' } },
    };
    render(<TableCell column={column} value="published" />);
    const badge = screen.getByText('published');
    // The green token must produce a green class, never the hardcoded muted fallback.
    expect(badge.className).toContain('green');
    expect(badge.className).not.toContain('bg-muted');
  });

  it('badge falls back to the muted class when no colour matches the value', () => {
    const column: BadgeColumnSchema = {
      ...baseColumn,
      type: 'badge',
      props: { colors: { published: 'green' } },
    };
    render(<TableCell column={column} value="archived" />);
    expect(screen.getByText('archived')).toHaveClass('bg-muted');
  });

  it('badge renders the lucide icon named by props.icons[value]', () => {
    const column: BadgeColumnSchema = {
      ...baseColumn,
      type: 'badge',
      props: { icons: { draft: 'pencil' }, colors: { draft: 'gray' } },
    };
    const { container } = render(<TableCell column={column} value="draft" />);
    // lucide-react renders an <svg> tagged with a `lucide` class.
    const svg = container.querySelector('svg');
    expect(svg).not.toBeNull();
    expect(svg?.getAttribute('class') ?? '').toContain('lucide');
  });

  it('boolean cell announces true/false via aria-label', () => {
    const column: BooleanColumnSchema = {
      ...baseColumn,
      type: 'boolean',
      props: { trueIcon: 'YES', falseIcon: 'NO' },
    };
    const { rerender } = render(<TableCell column={column} value={true} />);
    expect(screen.getByLabelText('true')).toHaveTextContent('YES');
    rerender(<TableCell column={column} value={false} />);
    expect(screen.getByLabelText('false')).toHaveTextContent('NO');
  });

  it('date cell formats ISO via mode', () => {
    const column: DateColumnSchema = {
      ...baseColumn,
      type: 'date',
      props: { mode: 'date', format: 'yyyy-MM-dd' },
    };
    render(<TableCell column={column} value="2026-01-15T00:00:00Z" />);
    expect(screen.getByRole('time')).toHaveAttribute('datetime');
  });

  it('number cell formats with thousands separator + suffix', () => {
    const column: NumberColumnSchema = {
      ...baseColumn,
      type: 'number',
      props: { decimals: 2, thousandsSeparator: '.', decimalSeparator: ',', suffix: ' kg' },
    };
    render(<TableCell column={column} value={1234.5} />);
    expect(screen.getByText('1.234,50 kg')).toBeInTheDocument();
  });

  it('icon cell exposes label', () => {
    const column: IconColumnSchema = {
      ...baseColumn,
      type: 'icon',
      props: { icon: '★', size: 'md' },
    };
    render(<TableCell column={column} value={null} />);
    expect(screen.getByLabelText('★')).toBeInTheDocument();
  });

  it('image cell honours circular shape', () => {
    const column: ImageColumnSchema = {
      ...baseColumn,
      type: 'image',
      props: { shape: 'circular', size: 24 },
    };
    const { container } = render(<TableCell column={column} value="/avatar.png" />);
    expect(container.querySelector('img')).toHaveClass('rounded-full');
  });

  it('relationship cell drills into attribute', () => {
    const column: RelationshipColumnSchema = {
      ...baseColumn,
      type: 'relationship',
      props: { relationship: 'team', attribute: 'name' },
    };
    render(<TableCell column={column} value={{ id: 1, name: 'Engineering' }} />);
    expect(screen.getByText('Engineering')).toBeInTheDocument();
  });

  it('renders em-dash for null date', () => {
    const column: DateColumnSchema = {
      ...baseColumn,
      type: 'date',
      props: { mode: 'datetime', format: '' },
    };
    render(<TableCell column={column} value={null} />);
    expect(screen.getByText('—')).toBeInTheDocument();
  });
});

// The default setup mocks `usePage` to `{ props: {} }` (en fallback). These
// tests stub it with an explicit `i18n.locale` to exercise locale-aware
// date/number/relative-time formatting — pt-BR must differ from en-US.
describe('TableCell — locale-aware formatting', () => {
  function mockLocale(locale: string | undefined) {
    pageMock.mockReturnValue(
      locale === undefined ? { props: {} } : { props: { i18n: { locale } } },
    );
  }

  afterEach(() => {
    pageMock.mockReturnValue({ props: {} });
  });

  it('boolean cell localizes the aria-label via the shared dictionary (pt-BR)', () => {
    const column: BooleanColumnSchema = {
      ...baseColumn,
      type: 'boolean',
      props: {},
    };
    // Stub usePage with both an active locale and the boolean labels so the
    // shared translator resolves them instead of the English fallback.
    pageMock.mockReturnValue({
      props: {
        i18n: {
          locale: 'pt_BR',
          translations: {
            table: { boolean: { true_label: 'sim', false_label: 'não' } },
          },
        },
      },
    });

    const { rerender } = render(<TableCell column={column} value={true} />);
    // Accessible name is now the Portuguese term, not the hardcoded 'true'.
    expect(screen.getByLabelText('sim')).toHaveTextContent('✓');
    expect(screen.queryByLabelText('true')).toBeNull();
    rerender(<TableCell column={column} value={false} />);
    expect(screen.getByLabelText('não')).toHaveTextContent('—');
    expect(screen.queryByLabelText('false')).toBeNull();
  });

  it('date cell formats the visible date in the active locale (pt_BR ≠ en)', () => {
    const column: DateColumnSchema = {
      ...baseColumn,
      type: 'date',
      props: { mode: 'date', format: 'Y-m-d', timezone: 'UTC' },
    };
    const value = '2026-01-15T00:00:00Z';

    mockLocale('en');
    const { unmount } = render(<TableCell column={column} value={value} />);
    const enText = screen.getByRole('time').textContent ?? '';
    unmount();

    mockLocale('pt_BR');
    render(<TableCell column={column} value={value} />);
    const ptText = screen.getByRole('time').textContent ?? '';

    // pt-BR renders the month name in Portuguese ("jan"), distinct from English.
    expect(ptText).not.toEqual(enText);
    expect(ptText.toLowerCase()).toContain('jan');
  });

  it('datetime cell honours the timezone prop and the active locale', () => {
    const column: DateColumnSchema = {
      ...baseColumn,
      type: 'date',
      props: { mode: 'datetime', format: 'Y-m-d H:i', timezone: 'America/Sao_Paulo' },
    };
    mockLocale('pt_BR');
    render(<TableCell column={column} value="2026-01-15T12:00:00Z" />);
    // 12:00 UTC → 09:00 in São Paulo (UTC-3); pt-BR uses 24h clock so "09".
    expect(screen.getByRole('time').textContent ?? '').toContain('09');
  });

  it('since/relative mode is localized via Intl.RelativeTimeFormat (no hardcoded " ago")', () => {
    const column: DateColumnSchema = {
      ...baseColumn,
      type: 'date',
      props: { mode: 'since', format: '' },
    };
    const threeDaysAgo = new Date(Date.now() - 3 * 24 * 60 * 60 * 1000).toISOString();

    mockLocale('en');
    const { unmount } = render(<TableCell column={column} value={threeDaysAgo} />);
    const enText = screen.getByRole('time').textContent ?? '';
    unmount();

    mockLocale('pt_BR');
    render(<TableCell column={column} value={threeDaysAgo} />);
    const ptText = screen.getByRole('time').textContent ?? '';

    expect(enText).toContain('ago');
    // Old code emitted the hardcoded "3d ago"; now en says "3 days ago".
    expect(enText).not.toContain('3d ago');
    expect(ptText).not.toEqual(enText);
    expect(ptText.toLowerCase()).toContain('dias');
  });

  it('money() currency prop renders via Intl.NumberFormat in the active locale', () => {
    const column: NumberColumnSchema = {
      ...baseColumn,
      type: 'number',
      props: { currency: 'EUR', decimals: 2 },
    };

    mockLocale('en');
    const { unmount } = render(<TableCell column={column} value={1234.5} />);
    const enText = screen.getByText(/1.*234/).textContent ?? '';
    unmount();

    mockLocale('pt_BR');
    render(<TableCell column={column} value={1234.5} />);
    const ptText = screen.getByText(/1.*234/).textContent ?? '';

    // Currency symbol must appear (was silently dropped before).
    expect(enText).toContain('€');
    expect(ptText).toContain('€');
    // Locale grouping differs: en-US "1,234.50" vs pt-BR "1.234,50".
    expect(ptText).not.toEqual(enText);
  });

  it('plain number cell groups in the active locale when no separators are set', () => {
    const column: NumberColumnSchema = {
      ...baseColumn,
      type: 'number',
      props: { decimals: 2 },
    };

    mockLocale('en');
    const { unmount } = render(<TableCell column={column} value={1234.5} />);
    const enText = screen.getByText(/234/).textContent ?? '';
    unmount();

    mockLocale('pt_BR');
    render(<TableCell column={column} value={1234.5} />);
    const ptText = screen.getByText(/234/).textContent ?? '';

    expect(enText).toEqual('1,234.50');
    expect(ptText).toEqual('1.234,50');
  });
});
