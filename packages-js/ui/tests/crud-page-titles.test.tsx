/**
 * CRUD page-title i18n regression suite.
 *
 * The default Inertia pages for create/edit/show build their visible H1
 * title. Before the fix the verb ('Create '/'Edit ') and the 'record'/'Record'
 * fallbacks were hardcoded English literals, so a pt_BR panel rendered
 * 'Create Usuário' / 'Edit record' / 'Record' regardless of locale. Each page
 * now routes the title through `useArqelTranslations()`:
 *   - arqel.pages.create = 'Create :label'
 *   - arqel.pages.edit   = 'Edit :label'
 *   - arqel.pages.record = 'Record'
 *   - arqel.pages.fallback = 'record'
 * keeping the (server-translated) resource label interpolated.
 */

import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ArqelCreatePage from '../src/pages/ArqelCreatePage.js';
import ArqelEditPage from '../src/pages/ArqelEditPage.js';
import ArqelShowPage from '../src/pages/ArqelShowPage.js';

const { pageMock } = vi.hoisted(() => ({ pageMock: vi.fn(() => ({ props: {} })) }));
vi.mock('@inertiajs/react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/react')>();
  return { ...actual, usePage: pageMock };
});

// useArqelForm pulls Inertia's useForm internally; stub it so the page shell
// renders without a live Inertia app context.
vi.mock('@arqel-dev/hooks', () => ({
  useArqelForm: () => ({
    data: {},
    setData: () => {},
    errors: {},
    processing: false,
  }),
}));

const PT_BR = {
  arqel: {
    pages: {
      create: 'Criar :label',
      edit: 'Editar :label',
      record: 'Registro',
      fallback: 'registro',
    },
  },
};

function withProps(props: Record<string, unknown>, locale: 'pt_BR' | null = 'pt_BR'): void {
  pageMock.mockReturnValue({
    props: locale
      ? { ...props, i18n: { locale, available: [locale], translations: PT_BR } }
      : props,
  });
}

afterEach(() => {
  pageMock.mockReset();
  pageMock.mockReturnValue({ props: {} });
});

describe('ArqelCreatePage title i18n', () => {
  it('localizes the verb and interpolates the resource label', () => {
    withProps({ resource: { label: 'Usuário', slug: 'users', panelPath: '/admin' }, fields: [] });
    render(<ArqelCreatePage />);
    expect(screen.getByRole('heading', { name: 'Criar Usuário' })).toBeInTheDocument();
  });

  it('localizes the fallback label when no resource label is present', () => {
    withProps({ fields: [] });
    render(<ArqelCreatePage />);
    expect(screen.getByRole('heading', { name: 'Criar registro' })).toBeInTheDocument();
  });

  it('falls back to English literals with no i18n prop', () => {
    withProps({ fields: [] }, null);
    render(<ArqelCreatePage />);
    expect(screen.getByRole('heading', { name: 'Create record' })).toBeInTheDocument();
  });
});

describe('ArqelEditPage title i18n', () => {
  it('localizes the verb and interpolates the resource label', () => {
    withProps({ resource: { label: 'Usuário', slug: 'users', panelPath: '/admin' }, fields: [] });
    render(<ArqelEditPage />);
    expect(screen.getByRole('heading', { name: 'Editar Usuário' })).toBeInTheDocument();
  });

  it('prefers the server-supplied recordTitle when present', () => {
    withProps({ recordTitle: 'Ada Lovelace', resource: { label: 'Usuário' }, fields: [] });
    render(<ArqelEditPage />);
    expect(screen.getByRole('heading', { name: 'Ada Lovelace' })).toBeInTheDocument();
  });

  it('falls back to English literals with no i18n prop', () => {
    withProps({ fields: [] }, null);
    render(<ArqelEditPage />);
    expect(screen.getByRole('heading', { name: 'Edit record' })).toBeInTheDocument();
  });
});

describe('ArqelShowPage title i18n', () => {
  it('localizes the Record fallback when neither title nor label is present', () => {
    withProps({ record: null, fields: [] });
    render(<ArqelShowPage />);
    expect(screen.getByRole('heading', { name: 'Registro' })).toBeInTheDocument();
  });

  it('falls back to the English Record literal with no i18n prop', () => {
    withProps({ record: null, fields: [] }, null);
    render(<ArqelShowPage />);
    expect(screen.getByRole('heading', { name: 'Record' })).toBeInTheDocument();
  });
});
