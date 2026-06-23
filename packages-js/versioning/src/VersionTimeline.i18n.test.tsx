/**
 * i18n regression suite for `<VersionTimeline>`.
 *
 * Round 2 localized only `formatRelativeTime`; the surrounding chrome
 * ('Initial' badge, 'Compare'/'Restore' buttons, the empty state, and the
 * aria-labels 'Loading versions' / 'Version history' / the per-item template)
 * stayed hardcoded English. Each assertion below would fail against that
 * version because the pt_BR dictionary had no effect on the rendered DOM.
 *
 * The shared dictionary is mocked under `props.i18n.translations` — the same
 * source `HandleArqelInertiaRequests` ships — so `t('arqel.versioning.initial')`
 * resolves `arqel.php => versioning.initial`.
 */

import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { type Version, VersionTimeline } from './VersionTimeline.js';

const { pageMock } = vi.hoisted(() => ({ pageMock: vi.fn(() => ({ props: {} })) }));
vi.mock('@inertiajs/react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/react')>();
  return { ...actual, usePage: pageMock };
});

const PT_BR = {
  arqel: {
    versioning: {
      initial: 'Inicial',
      compare: 'Comparar',
      restore: 'Restaurar',
      empty: 'Nenhuma versão ainda.',
      loading: 'Carregando versões',
      history: 'Histórico de versões',
      item_label: 'Versão :id por :user, :relative: :summary',
    },
  },
};

function usePtBr(): void {
  pageMock.mockReturnValue({
    props: { i18n: { locale: 'pt_BR', available: ['pt_BR'], translations: PT_BR } },
  });
}

afterEach(() => {
  pageMock.mockReset();
  pageMock.mockReturnValue({ props: {} });
});

const sampleVersions: ReadonlyArray<Version> = [
  {
    id: 1,
    created_at: '2026-04-30T12:00:00Z',
    changes_summary: 'Created record',
    user: { id: 7, name: 'Alice Doe' },
    is_initial: true,
  },
  {
    id: 2,
    created_at: '2026-05-01T08:00:00Z',
    changes_summary: 'Updated title and body',
    user: { id: 8, name: 'Bob Smith' },
    is_initial: false,
  },
];

describe('VersionTimeline i18n', () => {
  it('localizes the populated feed aria-label', () => {
    usePtBr();
    render(<VersionTimeline versions={sampleVersions} />);
    expect(screen.getByRole('feed', { name: 'Histórico de versões' })).toBeInTheDocument();
  });

  it('localizes the loading-state feed aria-label', () => {
    usePtBr();
    render(<VersionTimeline versions={[]} loading={true} />);
    expect(screen.getByRole('feed', { name: 'Carregando versões' })).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('localizes the empty state', () => {
    usePtBr();
    render(<VersionTimeline versions={[]} />);
    expect(screen.getByText('Nenhuma versão ainda.')).toBeInTheDocument();
  });

  it('localizes the Initial badge', () => {
    usePtBr();
    render(<VersionTimeline versions={sampleVersions} />);
    expect(screen.getByText('Inicial')).toBeInTheDocument();
  });

  it('localizes the Compare and Restore buttons', () => {
    usePtBr();
    render(
      <VersionTimeline versions={sampleVersions} onViewDiff={() => {}} onRestore={() => {}} />,
    );
    expect(screen.getAllByRole('button', { name: 'Comparar' }).length).toBeGreaterThan(0);
    expect(screen.getAllByRole('button', { name: 'Restaurar' }).length).toBeGreaterThan(0);
  });

  it('substitutes placeholders into the per-item accessible name', () => {
    usePtBr();
    const { container } = render(<VersionTimeline versions={[sampleVersions[1] as Version]} />);
    const li = container.querySelector('li[aria-label]');
    expect(li?.getAttribute('aria-label')).toContain('Versão 2 por Bob Smith');
    expect(li?.getAttribute('aria-label')).toContain('Updated title and body');
  });

  it('keeps English accessible names when no dictionary is present', () => {
    render(<VersionTimeline versions={sampleVersions} />);
    expect(screen.getByRole('feed', { name: 'Version history' })).toBeInTheDocument();
    expect(screen.getByText('Initial')).toBeInTheDocument();
  });
});
