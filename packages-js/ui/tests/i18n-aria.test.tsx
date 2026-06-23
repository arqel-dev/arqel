/**
 * i18n accessibility regression suite.
 *
 * Every accessible name (aria-label / sr-only legend / sr-only heading /
 * live-region text) emitted by `@arqel-dev/ui` chrome must route through
 * `useArqelTranslations()` so screen-reader users in non-English locales hear
 * a localized name. Before the fix these strings were hardcoded English
 * literals; each assertion below would have failed (the pt_BR override had no
 * effect on the rendered DOM).
 *
 * The shared dictionary is mocked under `props.i18n.translations` — the same
 * source `HandleArqelInertiaRequests` ships — keyed by lang-file basename, so
 * `t('table.bulk.label')` resolves `table.php => bulk.label`.
 */

import type { ColumnSchema } from '@arqel-dev/types/tables';
import type { TenantSummary } from '@arqel-dev/types/tenant';
import { render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ActionMenu } from '../src/action/ActionMenu.js';
import { FlashToast } from '../src/flash/FlashToast.js';
import { DataTable } from '../src/table/DataTable.js';
import { TableFilters } from '../src/table/TableFilters.js';
import { TablePagination } from '../src/table/TablePagination.js';
import { TableToolbar } from '../src/table/TableToolbar.js';
import { Breadcrumbs } from '../src/utility/Breadcrumbs.js';
import { StatCard } from '../src/widgets/StatCard.js';

const { pageMock } = vi.hoisted(() => ({ pageMock: vi.fn(() => ({ props: {} })) }));
vi.mock('@inertiajs/react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/react')>();
  return { ...actual, usePage: pageMock };
});

// pt_BR accessibility chrome, mirroring packages/core/resources/lang/pt_BR.
const PT_BR = {
  table: {
    bulk: {
      select_all: 'Selecionar todos',
      select_all_rows: 'Selecionar todas as linhas',
      select_row: 'Selecionar linha :id',
      label: 'Ações em massa',
      selected: '{one} :count selecionado|{other} :count selecionados',
      clear: 'Limpar',
    },
    column: { actions: 'Ações' },
    filters: { legend: 'Filtros', all: 'Todos' },
    pagination: {
      label: 'Paginação',
      previous: 'Anterior',
      next: 'Próximo',
      previous_page: 'Página anterior',
      next_page: 'Próxima página',
    },
  },
  arqel: {
    // The ActionMenu trigger/title resolves through arqel.actions.menu
    // (shared with the visible bottom-sheet title), not arqel.aria.*.
    actions: {
      menu: 'Ações',
    },
    aria: {
      flash_dismiss: 'Dispensar',
      stat_sparkline: 'Minigráfico de tendência',
      breadcrumb: 'Trilha de navegação',
      tenant_switch: 'Trocar de tenant (atual: :tenant)',
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

const columns: ColumnSchema[] = [
  {
    type: 'text',
    name: 'name',
    label: 'Nome',
    sortable: false,
    searchable: false,
    copyable: false,
    hidden: false,
    hiddenOnMobile: false,
    align: 'start',
    width: null,
    tooltip: null,
    props: {},
  },
];

const records = [{ id: 7, name: 'Ada' }];

describe('DataTable aria a11y i18n', () => {
  it('translates select-all, per-row select (:id) and the actions column header', () => {
    usePtBr();
    render(
      <DataTable
        columns={columns}
        records={records}
        enableSelection
        selectedIds={[]}
        onSelectionChange={() => {}}
        rowActions={() => <span>x</span>}
      />,
    );
    // Two surfaces (table + mobile cards) each render the same accessible names.
    expect(screen.getAllByLabelText('Selecionar todas as linhas').length).toBeGreaterThan(0);
    expect(screen.getAllByLabelText('Selecionar linha 7').length).toBeGreaterThan(0);
    expect(screen.getAllByLabelText('Ações').length).toBeGreaterThan(0);
  });

  it('falls back to English literals with no i18n prop', () => {
    render(
      <DataTable
        columns={columns}
        records={records}
        enableSelection
        selectedIds={[]}
        onSelectionChange={() => {}}
      />,
    );
    expect(screen.getAllByLabelText('Select all rows').length).toBeGreaterThan(0);
    expect(screen.getAllByLabelText('Select row 7').length).toBeGreaterThan(0);
  });
});

describe('table chrome aria a11y i18n', () => {
  it('translates the pagination <nav> landmark name', () => {
    usePtBr();
    render(
      <TablePagination
        meta={{ currentPage: 1, lastPage: 3, perPage: 10, total: 30 }}
        onPageChange={() => {}}
      />,
    );
    expect(screen.getByRole('navigation', { name: 'Paginação' })).toBeInTheDocument();
  });

  it('translates the bulk-actions <section> landmark name', () => {
    usePtBr();
    render(<TableToolbar selectedCount={2} bulkActions={<button type="button">go</button>} />);
    expect(screen.getByRole('region', { name: 'Ações em massa' })).toBeInTheDocument();
  });

  it('pluralizes the selected-count label per the active locale (1 vs N)', () => {
    usePtBr();
    const { rerender } = render(<TableToolbar selectedCount={1} />);
    // 1 -> singular form, never the "(s)" hack.
    expect(screen.getByText('1 selecionado')).toBeInTheDocument();
    rerender(<TableToolbar selectedCount={3} />);
    expect(screen.getByText('3 selecionados')).toBeInTheDocument();
  });

  it('pluralizes the selected-count fallback when no i18n prop is present', () => {
    const { rerender } = render(<TableToolbar selectedCount={1} />);
    expect(screen.getByText('1 selected')).toBeInTheDocument();
    rerender(<TableToolbar selectedCount={5} />);
    expect(screen.getByText('5 selected')).toBeInTheDocument();
  });

  it('translates the filters <fieldset> sr-only legend', () => {
    usePtBr();
    render(
      <TableFilters
        filters={[
          {
            type: 'text',
            name: 'q',
            label: 'Busca',
            props: {},
          } as never,
        ]}
        values={{}}
        onChange={() => {}}
      />,
    );
    expect(screen.getByRole('group', { name: 'Filtros' })).toBeInTheDocument();
  });
});

describe('ActionMenu trigger aria a11y i18n', () => {
  it('translates the dropdown / sheet trigger accessible name', () => {
    usePtBr();
    const actions = ['a', 'b', 'c', 'd'].map((n) => ({
      name: n,
      type: 'row' as const,
      label: n.toUpperCase(),
      color: 'primary' as const,
      variant: 'default' as const,
      method: 'POST' as const,
    }));
    render(<ActionMenu actions={actions} onInvoke={() => {}} />);
    // Desktop trigger + mobile bottom-sheet trigger both use the same name.
    expect(screen.getAllByLabelText('Ações').length).toBeGreaterThan(0);
  });
});

describe('FlashToast dismiss aria a11y i18n', () => {
  it('translates the close-button accessible name', () => {
    usePtBr();
    render(<FlashToast kind="info" message="Oi" onDismiss={() => {}} durationMs={0} />);
    expect(screen.getByRole('button', { name: 'Dispensar' })).toBeInTheDocument();
  });
});

describe('StatCard sparkline aria a11y i18n', () => {
  it('translates the sparkline svg accessible name', () => {
    usePtBr();
    render(
      <StatCard
        widget={{
          name: 'kpi',
          type: 'stat',
          value: 42,
          color: 'primary',
          chart: [1, 5, 2, 8, 3],
        }}
      />,
    );
    expect(screen.getByRole('img', { name: 'Minigráfico de tendência' })).toBeInTheDocument();
  });
});

describe('Breadcrumbs landmark aria a11y i18n', () => {
  it('translates the <nav> landmark name', () => {
    usePtBr();
    render(<Breadcrumbs items={[{ label: 'Início', url: '/' }, { label: 'Ada' }]} />);
    expect(screen.getByRole('navigation', { name: 'Trilha de navegação' })).toBeInTheDocument();
  });
});

describe('TenantSwitcher trigger aria a11y i18n', () => {
  it('translates the trigger accessible name with the current tenant', async () => {
    usePtBr();
    const { TenantSwitcher } = await import('../src/shell/TenantSwitcher.js');
    const current: TenantSummary = { id: 1, name: 'Acme' } as TenantSummary;
    const available = [current, { id: 2, name: 'Globex' } as TenantSummary];
    render(<TenantSwitcher current={current} available={available} />);
    const trigger = screen.getByTestId('tenant-switcher-trigger');
    expect(trigger).toHaveAttribute('aria-label', 'Trocar de tenant (atual: Acme)');
    // sanity: the visible label still shows the tenant name.
    expect(within(trigger).getByText('Acme')).toBeInTheDocument();
  });
});
